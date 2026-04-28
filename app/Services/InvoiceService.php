<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use InvalidArgumentException;

class InvoiceService
{
    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw new InvalidArgumentException('Only draft invoices can be issued');
        }

        $invoice->update([
            'status' => 'issued',
            'issued_at' => $invoice->issued_at ?? now(),
        ]);

        return $invoice->fresh(['trip', 'customer']);
    }

    public function markPaid(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'issued') {
            throw new InvalidArgumentException('Only issued invoices can be marked as paid');
        }

        $invoice->update(['status' => 'paid', 'paid_at' => now()]);

        return $invoice->fresh(['trip', 'customer']);
    }

    public function sendCqt(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'issued') {
            throw new InvalidArgumentException('Only issued invoices can be sent to tax authority');
        }

        return $invoice->load(['trip', 'customer']);
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'paid') {
            throw new InvalidArgumentException('Paid invoices cannot be cancelled');
        }

        $invoice->update(['status' => 'cancelled']);

        return $invoice->fresh(['trip', 'customer']);
    }
}
