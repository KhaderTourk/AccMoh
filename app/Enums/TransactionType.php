<?php

namespace App\Enums;

enum TransactionType: string
{
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
            self::ClientPayment => 'دفعة عميل',
            self::FamilyLoanReceived => 'اقتراض من العائلة',
            self::FamilyLoanGiven => 'إقراض فرد من العائلة',
            self::FamilyLoanRepaymentPaid => 'سداد قرض عائلي',
            self::FamilyLoanRepaymentReceived => 'استلام سداد عائلي',
            self::Expense => 'مصروف',
            self::TransferOut => 'تحويل صادر',
            self::TransferIn => 'تحويل وارد',
            self::TransferFee => 'رسوم تحويل',
            self::Adjustment => 'رصيد افتتاحي / تسوية',
            self::Reversal => 'إلغاء عملية',
        };
    }

    public function affectsCash(): bool
    {
        return true;
    }
}
