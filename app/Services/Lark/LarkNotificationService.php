<?php

declare(strict_types=1);

namespace App\Services\Lark;

use App\Jobs\Lark\SendLarkMessageJob;

class LarkNotificationService
{
    public function sendTextToChat(string $chatId, string $text): void
    {
        if ($chatId === '') {
            return;
        }

        SendLarkMessageJob::dispatch($chatId, 'text', [
            'text' => $text,
        ]);
    }

    public function notifyTripCreated(int $tripId, string $tripCode): void
    {
        $chatId = (string) config('lark.chat_ids.ops');
        if ($chatId === '') {
            return;
        }

        SendLarkMessageJob::dispatch($chatId, 'text', [
            'text' => sprintf('Trip created: #%d (%s)', $tripId, $tripCode),
        ]);
    }

    public function notifyPayrollApproved(int $payrollId, string $period): void
    {
        $chatId = (string) config('lark.chat_ids.payroll');
        if ($chatId === '') {
            return;
        }

        SendLarkMessageJob::dispatch($chatId, 'text', [
            'text' => sprintf('Payroll approved: #%d for %s', $payrollId, $period),
        ]);
    }

    public function notifyDriverAssigned(int $assignmentId, int $driverId, int $vehicleId): void
    {
        $chatId = (string) config('lark.chat_ids.fleet');
        if ($chatId === '') {
            return;
        }

        $card = [
            'config' => ['wide_screen_mode' => true],
            'header' => [
                'title' => [
                    'tag' => 'plain_text',
                    'content' => 'Driver Assigned',
                ],
            ],
            'elements' => [
                [
                    'tag' => 'div',
                    'text' => [
                        'tag' => 'lark_md',
                        'content' => sprintf(
                            "**Assignment:** %d\n**Driver ID:** %d\n**Vehicle ID:** %d",
                            $assignmentId,
                            $driverId,
                            $vehicleId
                        ),
                    ],
                ],
            ],
        ];

        SendLarkMessageJob::dispatch($chatId, 'interactive', [
            'card' => $card,
        ]);
    }
}
