<?php

namespace Database\Seeders;

use App\Enums\IssueCategory;
use App\Enums\IssueLogAction;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueSummaryStatus;
use App\Models\Issue;
use App\Models\IssueLog;
use App\Models\User;
use App\Services\IssueSummary\RuleBasedSummaryGenerator;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

class DemoIssueSeeder extends Seeder
{
    public function run(RuleBasedSummaryGenerator $generator): void
    {
        if (app()->isLocal()) {
            DB::transaction(function () use ($generator): void {
                $reporter = User::query()->where('email', 'demo@example.com')->lockForUpdate()->firstOrFail();
                $agent = User::query()->where('email', 'demo2@example.com')->firstOrFail();
                $random = new Randomizer(new Mt19937(42));
                $openedAt = now()->startOfHour();
    
                foreach ($this->fixtures() as $key => [$title, $description, $category, $priority, $status, $comments]) {
                    $openedAt = $openedAt->copy()->subDay();
                    $commentCount = $random->getInt(1, count($comments));
    
                    if (IssueLog::query()->where('details->demo_issue_key', $key)->exists()) {
                        continue;
                    }
    
                    $result = $generator->generate($title, $description, $category->value, $priority->value);
                    $highPriority = $priority === IssuePriority::High;
                    $resolvedAt = $openedAt->copy()->addHours($commentCount + 2);
                    $lastUpdate = match ($status) {
                        IssueStatus::Resolved   => $resolvedAt,
                        IssueStatus::InProgress => $openedAt->copy()->addHour(),
                        IssueStatus::Open       => $highPriority ? $openedAt->copy()->addMinutes(30) : $openedAt,
                    };
    
                    $issue = Issue::query()->make([
                        'title'                 => $title,
                        'description'           => $description,
                        'category'              => $category->value,
                        'priority'              => $priority->value,
                        'status'                => $status->value,
                        'needs_attention'       => $highPriority,
                        'summary'               => $result->summary,
                        'suggested_next_action' => $result->suggestedNextAction,
                        'summary_status'        => IssueSummaryStatus::Success->value,
                    ]);
                    $issue->forceFill(['created_at' => $openedAt, 'updated_at' => $lastUpdate])->save();
    
                    $this->recordLog($issue, $reporter, IssueLogAction::Created, [
                        'message'        => 'Issue created by '.$reporter->name,
                        'demo_issue_key' => $key,
                    ], $openedAt);
    
                    if ($highPriority) {
                        $this->recordLog($issue, $agent, IssueLogAction::Updated, [
                            'changes' => [
                                'priority'        => ['from' => IssuePriority::Medium->value, 'to' => IssuePriority::High->value],
                                'needs_attention' => ['from' => false, 'to' => true],
                            ],
                        ], $openedAt->copy()->addMinutes(30));
                    }
    
                    if ($status !== IssueStatus::Open) {
                        $this->recordLog($issue, $agent, IssueLogAction::Updated, [
                            'changes' => ['status' => ['from' => IssueStatus::Open->value, 'to' => IssueStatus::InProgress->value]],
                        ], $openedAt->copy()->addHour());
                    }
    
                    foreach (array_slice($comments, 0, $commentCount) as $index => $body) {
                        $issue->comments()->make([
                            'author_name' => ($index % 2 === 0 ? $agent : $reporter)->name,
                            'body'        => $body,
                        ])->forceFill(['created_at' => $openedAt->copy()->addHours($index + 2)])->save();
                    }
    
                    if ($status === IssueStatus::Resolved) {
                        $this->recordLog($issue, $agent, IssueLogAction::Updated, [
                            'changes' => ['status' => ['from' => IssueStatus::InProgress->value, 'to' => IssueStatus::Resolved->value]],
                        ], $resolvedAt);
                    }
                }
            });
        }
    }

    /** @param array<string, mixed> $details */
    private function recordLog(Issue $issue, User $actor, IssueLogAction $action, array $details, CarbonInterface $time): void
    {
        $issue->logs()->make([
            'user_id' => $actor->getKey(),
            'action'  => $action->value,
            'details' => $details,
        ])->forceFill(['created_at' => $time, 'updated_at' => $time])->save();
    }

    /** @return array<string, array{string, string, IssueCategory, IssuePriority, IssueStatus, list<string>}> */
    private function fixtures(): array
    {
        return [
            'duplicate-charge' => [
                'Customer was charged twice',
                'A customer reports two posted charges for invoice INV-4837 after retrying checkout. Both charges appear on the same statement.',
                IssueCategory::Billing, IssuePriority::High, IssueStatus::Open,
                [
                    'I have both payment references and will compare them against the invoice.',
                    'The customer confirmed that both charges have posted.',
                    'The invoice shows only one order and one shipment.',
                    'I asked the customer to keep both transaction references for the review.',
                ],
            ],
            'manager-sign-in' => [
                'Store managers cannot sign in to the portal',
                'Three store managers get an access denied message after signing in to the support portal. The issue started after the morning role sync.',
                IssueCategory::Access, IssuePriority::High, IssueStatus::InProgress,
                [
                    'I reproduced the error using a store manager account.',
                    'The affected accounts still appear in the manager group.',
                    'I am comparing the current role mapping with yesterday’s snapshot.',
                    'The team is using the shared support mailbox while access is investigated.',
                ],
            ],
            'inventory-export' => [
                'Morning inventory export arrived late',
                'The daily inventory export arrived 25 minutes after its scheduled delivery. The warehouse team delayed the reconciliation report while waiting for it.',
                IssueCategory::Operations, IssuePriority::Medium, IssueStatus::InProgress,
                [
                    'The export finished successfully, but the job started late.',
                    'The warehouse team confirmed the file contains the expected rows.',
                    'I am checking the scheduler logs for the delayed start.',
                    'The next scheduled run is set for the usual time tomorrow.',
                ],
            ],
            'invoice-address' => [
                'Invoice shows an old billing address',
                'An account administrator reported that an April invoice shows the previous office address. The billing profile already has the current address.',
                IssueCategory::Billing, IssuePriority::Low, IssueStatus::Resolved,
                [
                    'I confirmed the new billing address with the account administrator.',
                    'The invoice used an address saved before the profile update.',
                    'A corrected invoice was issued and sent to the requester.',
                    'The requester confirmed the corrected invoice was received.',
                ],
            ],
            'expired-invitation' => [
                'New hire’s invitation link has expired',
                'A new hire opened their portal invitation after it had expired. The hiring manager has asked for a replacement invitation.',
                IssueCategory::Access, IssuePriority::Medium, IssueStatus::Open,
                [
                    'I am confirming the work email before sending a replacement.',
                    'The manager confirmed the address on the original invitation.',
                    'The invitation record shows it was never accepted.',
                    'I will check that the replacement link reaches the correct inbox.',
                ],
            ],
            'warehouse-labels' => [
                'Shipping label queue stopped processing',
                'The label queue stopped processing shipments from the East warehouse. Dispatch resumed after the print service was restarted.',
                IssueCategory::Operations, IssuePriority::High, IssueStatus::Resolved,
                [
                    'I confirmed the queue had stalled behind a single print job.',
                    'The print service was restarted and the pending labels cleared.',
                    'The warehouse confirmed that all held shipments now have labels.',
                    'I recorded the restart time for the next operations review.',
                ],
            ],
            'report-filename' => [
                'Weekly report filename has the wrong date',
                'The weekly report contains the correct figures, but the filename shows last week’s date. The report attachment is still readable.',
                IssueCategory::Operations, IssuePriority::Low, IssueStatus::Open,
                [
                    'I reproduced the filename discrepancy using the latest export.',
                    'The date range inside the report is correct.',
                    'I will check where the filename date comes from.',
                    'The reporting team can use the attachment while we investigate.',
                ],
            ],
            'refund-receipt' => [
                'Refund receipt was never emailed',
                'A customer sees the refund on their statement but did not receive a receipt. Support has the original invoice and refund reference.',
                IssueCategory::Billing, IssuePriority::Medium, IssueStatus::InProgress,
                [
                    'I verified that the refund record is complete.',
                    'The customer confirmed the email address on the order.',
                    'I am checking whether the receipt notification was delivered.',
                    'The invoice and refund reference both match the customer’s report.',
                ],
            ],
            'reset-email' => [
                'Password reset email landed in spam',
                'A team member could not find the password reset email until they checked spam. They used the link and regained access.',
                IssueCategory::Access, IssuePriority::Low, IssueStatus::Resolved,
                [
                    'The team member confirmed they can sign in again.',
                    'The reset link was still valid when the message was found.',
                    'I recorded the sender address for the email delivery review.',
                    'The requester confirmed no further assistance is needed.',
                ],
            ],
            'daily-digest' => [
                'Daily digest omitted two completed shipments',
                'The daily digest was missing two shipments completed just before the reporting cutoff. Both shipments were included after the report was rerun.',
                IssueCategory::Operations, IssuePriority::Medium, IssueStatus::Resolved,
                [
                    'I matched the missing entries against the shipment completion times.',
                    'The rerun returned both shipments in the corrected digest.',
                    'The operations lead confirmed the corrected totals.',
                    'I noted the cutoff timing for the next reporting review.',
                ],
            ],
        ];
    }
}
