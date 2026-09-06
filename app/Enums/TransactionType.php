<?php

namespace App\Enums;

enum TransactionType: string
{
    case IncomingPayment = 'incoming_payment';
    case OutgoingPayment = 'outgoing_payment';
    case ClientPayment = 'client_payment';
    case FamilyLoanReceived = 'family_loan_received';
    case FamilyLoanGiven = 'family_loan_given';
    case FamilyLoanRepaymentPaid = 'family_loan_repayment_paid';
    case FamilyLoanRepaymentReceived = 'family_loan_repayment_received';
    case Expense = 'expense';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case TransferFee = 'transfer_fee';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::IncomingPayment, self::ClientPayment, self::FamilyLoanReceived, self::FamilyLoanRepaymentReceived => 'دفعة واردة',
            self::OutgoingPayment, self::Expense, self::FamilyLoanGiven, self::FamilyLoanRepaymentPaid => 'دفعة صادرة',
            self::TransferOut => 'تحويل صادر',
            self::TransferIn => 'تحويل وارد',
            self::TransferFee => 'رسوم تحويل',
            self::Adjustment => 'رصيد افتتاحي / تسوية',
            self::Reversal => 'إلغاء عملية',
        };
    }

    public function isIncomingCash(): bool
    {
        return in_array($this, [
            self::IncomingPayment,
            self::ClientPayment,
            self::FamilyLoanReceived,
            self::FamilyLoanRepaymentReceived,
        ], true);
    }

    public function isOutgoingCash(): bool
    {
        return in_array($this, [
            self::OutgoingPayment,
            self::Expense,
            self::FamilyLoanGiven,
            self::FamilyLoanRepaymentPaid,
        ], true);
    }

    public function affectsCash(): bool
    {
        return true;
    }
}
