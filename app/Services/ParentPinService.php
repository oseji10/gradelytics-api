<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use App\Models\ParentAccess;

class ParentPinService
{
    private $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function generateSecurePin($length = 10)
    {
        $pin = '';
        for ($i = 0; $i < $length; $i++) {
            $pin .= $this->characters[random_int(0, strlen($this->characters) - 1)];
        }
        return $pin;
    }

    public function createPinRecord($schoolId, $phone, $sessionId, $termId, $paymentMethod, $amount)
    {
        $plainPin = $this->generateSecurePin();

        $record = ParentAccess::create([
            'schoolId' => $schoolId,
            'phoneNumber' => $phone,
            'academicYearId' => $sessionId,
            'termId' => $termId,
            'pinHash' => Hash::make($plainPin),
            'pinLast4' => substr($plainPin, -4),
            'paymentMethod' => $paymentMethod,
            'amountPaid' => $amount,
            'expiresAt' => now()->addMonths(4),
            'isActive' => true,
        ]);

        return [
            'plain_pin' => $plainPin,
            'record' => $record
        ];
    }
}