<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\School;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    /**
     * Store a new invoice with items, optional tax, and amountPaid.
     */
    public function index(){
        $invoices = Invoice::with('items', 'currencyDetail', 'customer')->get();
        return response()->json($invoices);
    }
    public function store(Request $request)
{
    $user = Auth::user();

    if (!$user->canCreateInvoice()) {
        return response()->json([
            'message' => 'Sorry you can\'t add any more invoices. Upgrade to premium to generate more invoices.'
        ], 403);
    }

    $schoolId = $request->header('X-School-ID');

    $request->validate([
        'invoiceId'              => 'required|unique:invoices,invoiceId',
        'projectName'            => 'nullable|string|max:255',
        'invoiceDate'            => 'nullable|date',
        'dueDate'                => 'nullable|date',
        'currency'               => 'nullable|exists:currencies,currencyId',
        'schoolId'               => 'nullable|exists:tenants,schoolId',
        'createdBy'              => 'nullable|exists:users,id',
        'taxPercentage'          => 'nullable|numeric|min:0',
        'discountPercentage'     => 'nullable|numeric|min:0|max:100',
        'amountPaid'             => 'nullable|numeric|min:0',
        'items'                  => 'required|array|min:1',
        'items.*.itemDescription'=> 'required|string',
        'items.*.amount'         => 'required|numeric|min:0',     // unit price
        'items.*.quantity'       => 'required|numeric|integer|min:1',
        // 'items.*.discountAmount'  → removed from validation & usage
    ]);

    // ────────────────────────────────────────────────
    // 1. Calculate subtotal (gross before any discount/tax)
    // ────────────────────────────────────────────────
    $subtotal = 0;
    foreach ($request->items as $item) {
        $qty    = (float) ($item['quantity'] ?? 1);
        $amount = (float) ($item['amount'] ?? 0);   // unit price
        $subtotal += $qty * $amount;
    }

    // ────────────────────────────────────────────────
    // 2. Global discount (applied BEFORE tax - common in NG)
    // ────────────────────────────────────────────────
    $discountPercentage = (float) ($request->input('discountPercentage', 0));
    $discountAmount     = $subtotal * ($discountPercentage / 100);
    $amountAfterDiscount = max(0, $subtotal - $discountAmount);

    // ────────────────────────────────────────────────
    // 3. Tax (VAT) on discounted amount
    // ────────────────────────────────────────────────
    $taxPercentage = (float) ($request->input('taxPercentage', 0));
    $taxAmount     = $amountAfterDiscount * ($taxPercentage / 100);

    // ────────────────────────────────────────────────
    // 4. Grand total & balance
    // ────────────────────────────────────────────────
    $totalAmount = $amountAfterDiscount + $taxAmount;
    $amountPaid  = (float) ($request->input('amountPaid', 0));
    $balanceDue  = max(0, $totalAmount - $amountPaid);

    // ────────────────────────────────────────────────
    // 5. Get tenant default currency if needed
    // ────────────────────────────────────────────────
    $tenant = Tenant::where('schoolId', $schoolId)->first();
    $currency = $request->input('currency', $tenant ? $tenant->currency : null);

    // ────────────────────────────────────────────────
    // 6. Create main invoice record
    // ────────────────────────────────────────────────
    $invoice = Invoice::create(array_merge(
        $request->only([
            'invoiceId',
            'userGeneratedInvoiceId',
            'projectName',
            'invoiceDate',
            'dueDate',
            'invoicePassword',
            'notes',
            'accountName',
            'accountNumber',
            'bank',
            'taxPercentage',
            'discountPercentage',
            'customerId'
        ]),
        [
            'subtotal'       => $subtotal,           // added - good for reporting / PDF
            'discountAmount' => $discountAmount,     // added - optional but useful
            'taxAmount'      => $taxAmount,          // added
            'totalAmount'    => $totalAmount,
            'amountPaid'     => $amountPaid,
            'balanceDue'     => $balanceDue,
            'schoolId'       => $schoolId,
            'createdBy'      => auth()->id(),
            'currency'       => $currency,
            'status'         => $balanceDue >= $totalAmount ? 'UNPAID' : ($amountPaid > 0 ? 'PARTIAL' : 'PAID'),
        ]
    ));

    // ────────────────────────────────────────────────
    // 7. Create invoice items (NO per-line discount anymore)
    // ────────────────────────────────────────────────
    foreach ($request->items as $item) {
        $invoice->items()->create([
            'itemDescription' => $item['itemDescription'],
            'quantity'        => (int) ($item['quantity'] ?? 1),
            'amount'          => (float) ($item['amount'] ?? 0),  // unit price
            // 'discountAmount'  → removed
        ]);
    }

    // Reload with relations for response
    $invoice->load('items');

    return response()->json([
        'message' => 'Invoice created successfully',
        'invoice' => $invoice
    ], 201);
}
    /**
     * Get all invoices for the authenticated user.
     */
    public function getUserInvoices(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $userId = Auth::id();

        $invoices = Invoice::with('items', 'currencyDetail', 'customer')
            ->where('createdBy', $userId)
            ->where('schoolId', $schoolId)
            ->get();

        return response()->json($invoices);
    }


    public function getUserReceipts(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $userId = Auth::id();

        $invoices = Invoice::with('items', 'currencyDetail', 'customer')
            ->where('createdBy', $userId)
            ->where('schoolId', $schoolId)
            ->where('status', 'PAID')
            ->orWhere('status', 'PARTIAL_PAYMENT')
            ->get();

        return response()->json($invoices);
    }

     public function getLast5UserInvoices(Request $request)
{
    $schoolId = $request->header('X-School-ID');
    $userId = Auth::id();

    if (!$schoolId) {
        return response()->json([
            'message' => 'Tenant ID is missing'
        ], 400);
    }

    $invoices = Invoice::with([
            'items',
            'currencyDetail',
            'customer',
        ])
        ->where('createdBy', $userId)
        ->where('schoolId', $schoolId)
        ->latest()   // orders by created_at desc
        ->limit(5)
        ->get();

    return response()->json($invoices, 200);
}


// public function invoiceSummary(Request $request)
// {
//     $schoolId = $request->header('X-School-ID');
//     $userId = Auth::id();

//     if (!$schoolId) {
//         return response()->json([
//             'message' => 'Tenant ID is missing'
//         ], 400);
//     }

//     $summary = Invoice::where('schoolId', $schoolId)
//         ->where('createdBy', $userId)
//         ->selectRaw('
//             COALESCE(SUM(amountPaid), 0) as collected,
//             COALESCE(SUM(balanceDue), 0) as outstanding
//         ')
//         ->first();

//     return response()->json([
//         'collected' => (float) $summary->collected,
//         'outstanding' => (float) $summary->outstanding,
//     ]);


public function invoiceSummary(Request $request)
{
    $schoolId = $request->header('X-School-ID');
    $userId = Auth::id();

    if (!$schoolId) {
        return response()->json([
            'message' => 'Tenant ID is missing'
        ], 400);
    }

    // 1. Get the aggregated amounts
    $amounts = Invoice::where('schoolId', $schoolId)
        ->where('createdBy', $userId)
        ->selectRaw('
            COALESCE(SUM(amountPaid), 0) AS collected,
            COALESCE(SUM(balanceDue), 0) AS outstanding
        ')
        ->first();

    // 2. Get currency from any one invoice (preferably the latest)
    $currencyInfo = Invoice::where('schoolId', $schoolId)
        ->where('createdBy', $userId)
        ->join('currencies', 'invoices.currency', '=', 'currencies.currencyId')
        ->select('currencies.currencyCode AS currency_code', 'currencies.currencySymbol AS currency_symbol')
        ->orderBy('invoices.created_at', 'desc') // get from most recent invoice
        ->first();

    // If no invoices exist
    if (!$amounts) {
        return response()->json([
            'collected'       => 0.0,
            'outstanding'     => 0.0,
            'currency_code'   => 'USD',
            'currency_symbol' => '$',
        ]);
    }

    return response()->json([
        'collected'       => (float) $amounts->collected,
        'outstanding'     => (float) $amounts->outstanding,
        'currency_code'   => $currencyInfo?->currency_code ?? 'USD',
        'currency_symbol' => $currencyInfo?->currency_symbol ?? $this->getFallbackSymbol($currencyInfo?->currency_code ?? 'USD'),
    ]);
}

private function getFallbackSymbol(string $code): string
{
    return match (strtoupper($code)) {
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'NGN' => '₦',
        'GHS' => 'GH₵',
        'ZAR' => 'R',
        'KES' => 'KSh',
        default => strtoupper($code),
    };
}



public function adminInvoiceSummary(Request $request)
{
    // IMPORTANT: disable tenant scope if it exists
    Invoice::withoutGlobalScopes();

    $summaries = Invoice::query()
        ->leftJoin('currencies', 'invoices.currency', '=', 'currencies.currencyId')
        ->selectRaw('
            currencies.currencyCode AS currency_code,
            currencies.currencySymbol AS currency_symbol,
            currencies.country AS country,
            currencies.currencySymbol AS currency_symbol,
            SUM(invoices.amountPaid)  AS collected,
            SUM(invoices.balanceDue) AS outstanding
        ')
        ->groupBy(
            'currencies.currencyCode',
            'currencies.currencySymbol',
            'currencies.country',
        )
        ->get();

    return response()->json(
        $summaries->map(fn ($row) => [
            'currency_code'   => $row->currency_code,
            'currency_symbol' => $row->currency_symbol
                ?? $this->getFallbackSymbol($row->currency_code),
            'country'         => $row->country,
            'collected'       => (float) $row->collected,
            'outstanding'     => (float) $row->outstanding,
        ])
    );
}


    /**
     * Get a single invoice by tenant ID.
     */
    public function getInvoiceByTenant($schoolId)
    {
        $invoice = Invoice::with('items', 'currencyDetail',)
            ->where('schoolId', $schoolId)
            ->first();

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found for this tenant'], 404);
        }

        return response()->json($invoice);
    }

public function getInvoiceByInvoiceId(Request $request, $invoiceId)
    {
        $schoolId = $request->header('X-School-ID');
        $userId = Auth::id();

        $invoice = Invoice::with('items', 'currencyDetail', 'tenant', 'customer', 'creator')
            ->where('createdBy', $userId)
            ->where('invoiceId', $invoiceId)
            ->where('schoolId', $schoolId)
            ->get();

        return response()->json($invoice);
    }


    public function getInvoiceByInvoiceIdForAdmin(Request $request, $invoiceId)
    {
        // $schoolId = $request->header('X-School-ID');
        // $userId = Auth::id();

        $invoice = Invoice::with('items', 'currencyDetail', 'tenant', 'customer', 'creator')
            // ->where('createdBy', $userId)
            ->where('invoiceId', $invoiceId)
            // ->where('schoolId', $schoolId)
            ->get();

        return response()->json($invoice);
    }

    public function getReceiptByReceiptId(Request $request, $receiptId)
    {
        $schoolId = $request->header('X-School-ID');
        $userId = Auth::id();

        $invoice = Invoice::with('items', 'currencyDetail', 'tenant', 'customer', 'creator')
            ->where('createdBy', $userId)
            ->where('receiptId', $receiptId)
            ->where('schoolId', $schoolId)
            ->get();

        return response()->json($invoice);
    }

public function getInvoiceAndReceiptsByCustomerId(Request $request, $customerId)
    {
        $schoolId = $request->header('X-School-ID');
        $userId = Auth::id();

        $invoice = Invoice::with('items', 'currencyDetail', 'tenant', 'customer')
            ->where('createdBy', $userId)
            ->where('customerId', $customerId)
            ->where('schoolId', $schoolId)
            ->get();

        return response()->json($invoice);
    }


    // public function getInvoiceAndReceiptsByCustomerId(Request $request, $customerId)
    // {
    //     $schoolId = $request->header('X-School-ID');
    //     $userId = Auth::id();

    //     $invoice = Invoice::with('items', 'currencyDetail', 'tenant', 'customer')
    //         ->where('createdBy', $userId)
    //         ->where('customerId', $customerId)
    //         ->where('schoolId', $schoolId)
    //         ->get();

    //     return response()->json($invoice);
    // }


    public function getInvoicesForCustomer(Request $request, $customerId)
    {
        $schoolId = $request->header('X-School-ID');
        $userId = Auth::id();

        $invoices = Invoice::with('items', 'currencyDetail', 'tenant', 'customer')
            ->where('createdBy', $userId)
            ->where('customerId', $customerId)
            ->where('status', 'UNPAID')
            ->where('schoolId', $schoolId)
            ->get();

        return response()->json($invoices);
    }


public function getReceiptsForCustomer(Request $request, $customerId)
{
    $schoolId = $request->header('X-School-ID');
    $userId = Auth::id();

    $receipts = Invoice::with(['items', 'currencyDetail', 'tenant', 'customer'])
        ->where('createdBy', $userId)
        ->where('customerId', $customerId)
        ->whereIn('status', ['PAID', 'PARTIAL_PAYMENT'])
        ->where('schoolId', $schoolId)
        ->get();

    return response()->json($receipts);
}





//     public function updateInvoiceStatus(Request $request, $invoiceId)
// {
//     $invoice = Invoice::where('invoiceId', $invoiceId)->first();

//     if (!$invoice) {
//         return response()->json([
//             'message' => 'Invoice not found'
//         ], 404);
//     }

//     $validated = $request->validate([
//         'status' => 'required|string',
//         'amountPaid' => 'nullable|numeric|min:0'
//     ]);

//     $status = strtoupper($validated['status']);

//     // 🔹 PAID: clear balance, move everything to amountPaid
//     if ($status === 'PAID') {
//         $invoice->amountPaid = $invoice->amountPaid + $invoice->balanceDue;
//         $invoice->balanceDue = 0;
//         $invoice->status = 'PAID';
//     }

//     // 🔹 PARTIAL PAYMENT: use amount sent from frontend
//     elseif ($status === 'PARTIAL_PAYMENT') {
//         if (!isset($validated['amountPaid'])) {
//             return response()->json([
//                 'message' => 'amountPaid is required for partial payment'
//             ], 422);
//         }

//         $partialAmount = (float) $validated['amountPaid'];

//         if ($partialAmount > $invoice->balanceDue) {
//             return response()->json([
//                 'message' => 'Amount paid cannot exceed balance due'
//             ], 422);
//         }

//         $invoice->amountPaid += $partialAmount;
//         $invoice->balanceDue -= $partialAmount;
//         $invoice->status = 'PARTIAL_PAYMENT';
//     }

//     // 🔹 Other statuses (optional handling)
//     else {
//         $invoice->status = $status;
//     }

//     $invoice->save();

//     return response()->json([
//         'message' => 'Invoice updated successfully',
//         'invoice' => $invoice
//     ]);
// }

public function updateInvoiceStatus(Request $request, $invoiceId)
{
    $invoice = Invoice::where('invoiceId', $invoiceId)->first();

    if (!$invoice) {
        return response()->json([
            'message' => 'Invoice not found'
        ], 404);
    }

    $validated = $request->validate([
        'status' => 'required|string',
        'amountPaid' => 'nullable|numeric|min:0'
    ]);

    $status = strtoupper($validated['status']);

    /**
     * Generate receipt ID ONLY if:
     * - Status is PAID or PARTIAL_PAYMENT
     * - AND receiptId does not already exist
     */
    $receiptId = strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999);
    $shouldGenerateReceipt =
        in_array($status, ['PAID', 'PARTIAL_PAYMENT']) &&
        empty($invoice->receiptId);

    if ($shouldGenerateReceipt) {
        $invoice->receiptId = 'RCPT-' . $receiptId;
    }

    // 🔹 PAID: clear balance, move everything to amountPaid
    if ($status === 'PAID') {
        $invoice->amountPaid += $invoice->balanceDue;
        $invoice->balanceDue = 0;
        $invoice->status = 'PAID';
    }

    // 🔹 PARTIAL PAYMENT
    elseif ($status === 'PARTIAL_PAYMENT') {
        if (!isset($validated['amountPaid'])) {
            return response()->json([
                'message' => 'amountPaid is required for partial payment'
            ], 422);
        }

        $partialAmount = (float) $validated['amountPaid'];

        if ($partialAmount > $invoice->balanceDue) {
            return response()->json([
                'message' => 'Amount paid cannot exceed balance due'
            ], 422);
        }

        $invoice->amountPaid += $partialAmount;
        $invoice->balanceDue -= $partialAmount;
        $invoice->status = 'PARTIAL_PAYMENT';
    }

    // 🔹 Other statuses
    else {
        $invoice->status = $status;
    }

    $invoice->save();

    return response()->json([
        'message' => 'Invoice updated successfully',
        'invoice' => $invoice
    ]);
}

//ANALYTICS DATA   /**
public function invoiceStatusBreakdown()
{
    $data = Invoice::query()
        ->withoutGlobalScopes()
        ->selectRaw('status, COUNT(*) as count')
        ->groupBy('status')
        ->get();

    return response()->json($data);
}



public function overdueInvoicesSummary()
{
    $today = Carbon::today();

    $data = [
        '1_7_days' => Invoice::withoutGlobalScopes()
            ->where('status', 'overdue')
            ->whereBetween('dueDate', [
                $today->copy()->subDays(7),
                $today->copy()->subDay()
            ])->count(),

        '8_30_days' => Invoice::withoutGlobalScopes()
            ->where('status', 'overdue')
            ->whereBetween('dueDate', [
                $today->copy()->subDays(30),
                $today->copy()->subDays(8)
            ])->count(),

        '31_plus_days' => Invoice::withoutGlobalScopes()
            ->where('status', 'overdue')
            ->where('dueDate', '<', $today->copy()->subDays(30))
            ->count(),
    ];

    return response()->json($data);
}


public function currencyDistribution()
{
    $data = Invoice::query()
        ->withoutGlobalScopes()
        ->join('currencies', 'invoices.currency', '=', 'currencies.currencyId')
        ->selectRaw('
            currencies.currencyCode as currency,
            SUM(invoices.amountPaid) as total
        ')
        ->groupBy('currencies.currencyCode')
        ->orderByDesc('total')
        ->get();

    return response()->json($data);
}


public function topTenants()
{
    $data = Invoice::query()
        ->withoutGlobalScopes()
        ->join('tenants', 'invoices.schoolId', '=', 'tenants.schoolId')
        ->selectRaw('
            tenants.tenantName,
            SUM(invoices.amountPaid) as revenue
        ')
        ->groupBy('tenants.schoolId', 'tenants.tenantName')
        ->orderByDesc('revenue')
        ->limit(5)
        ->get();

    return response()->json($data);
}


public function revenueTrends()
{
    $data = Invoice::query()
        ->withoutGlobalScopes()
        ->where('status', 'paid')
        ->selectRaw('
            DATE_FORMAT(created_at, "%Y-%m") as period,
            SUM(amountPaid) as revenue
        ')
        ->groupBy('period')
        ->orderBy('period')
        ->get();

    return response()->json($data);
}

public function paymentMethodBreakdown()
{
    $data = Invoice::query()
        ->withoutGlobalScopes()
        ->selectRaw('paymentMethod, COUNT(*) as count')
        ->groupBy('paymentMethod')
        ->get();

    return response()->json($data);
}


}
