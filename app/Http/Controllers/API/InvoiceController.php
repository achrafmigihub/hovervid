<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices with filtering and pagination.
     */
    public function index(Request $request)
    {
        try {
            $query = Invoice::with('user');

            // Apply search filter
            if ($request->filled('q')) {
                $search = $request->input('q');
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'ILIKE', "%{$search}%")
                      ->orWhere('description', 'ILIKE', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('name', 'ILIKE', "%{$search}%")
                                    ->orWhere('email', 'ILIKE', "%{$search}%");
                      });
                });
            }

            // Apply status filter
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Apply user filter (if specified)
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            // Apply sorting
            $sortBy = $request->input('sortBy', 'created_at');
            $orderBy = $request->input('orderBy', 'desc');
            
            $allowedSorts = ['id', 'invoice_number', 'status', 'total', 'balance', 'issued_date', 'due_date', 'created_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $orderBy);
            }

            // Get total count before pagination
            $totalInvoices = $query->count();

            // Apply pagination
            $itemsPerPage = min(100, max(1, intval($request->input('itemsPerPage', 10))));
            $page = max(1, intval($request->input('page', 1)));
            
            if ($itemsPerPage != -1) {
                $query->offset(($page - 1) * $itemsPerPage)->limit($itemsPerPage);
            }

            $invoices = $query->get()->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoiceStatus' => ucwords(str_replace('_', ' ', $invoice->status)),
                    'total' => (float) $invoice->total,
                    'balance' => (float) $invoice->balance,
                    'issuedDate' => $invoice->issued_date->format('M d, Y'),
                    'dueDate' => $invoice->due_date->format('M d, Y'),
                    'user' => [
                        'id' => $invoice->user->id,
                        'name' => $invoice->user->name,
                        'email' => $invoice->user->email,
                    ],
                    'invoice_number' => $invoice->invoice_number,
                    'description' => $invoice->description,
                ];
            });

            return response()->json([
                'invoices' => $invoices,
                'totalInvoices' => $totalInvoices,
                'page' => $page,
                'lastPage' => $itemsPerPage != -1 ? ceil($totalInvoices / $itemsPerPage) : 1,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching invoices', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to fetch invoices',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while fetching invoices'
            ], 500);
        }
    }

    /**
     * Store a newly created invoice.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'total' => 'required|numeric|min:0',
                'balance' => 'sometimes|numeric|min:0',
                'issued_date' => 'required|date',
                'due_date' => 'required|date|after_or_equal:issued_date',
                'description' => 'sometimes|string|max:1000',
                'status' => 'sometimes|in:draft,sent,paid,partial_payment,past_due,downloaded',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['invoice_number'] = Invoice::generateInvoiceNumber();
            $data['balance'] = $data['balance'] ?? $data['total'];

            $invoice = Invoice::create($data);
            $invoice->load('user');

            Log::info('Invoice created', [
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->user_id
            ]);

            return response()->json([
                'message' => 'Invoice created successfully',
                'invoice' => $invoice
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating invoice', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to create invoice',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while creating the invoice'
            ], 500);
        }
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice)
    {
        try {
            $invoice->load('user');
            
            return response()->json([
                'invoice' => $invoice
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching invoice', [
                'invoice_id' => $invoice->id,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to fetch invoice',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while fetching the invoice'
            ], 500);
        }
    }

    /**
     * Update the specified invoice.
     */
    public function update(Request $request, Invoice $invoice)
    {
        try {
            $validator = Validator::make($request->all(), [
                'total' => 'sometimes|numeric|min:0',
                'balance' => 'sometimes|numeric|min:0',
                'issued_date' => 'sometimes|date',
                'due_date' => 'sometimes|date',
                'description' => 'sometimes|string|max:1000',
                'status' => 'sometimes|in:draft,sent,paid,partial_payment,past_due,downloaded',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $invoice->update($validator->validated());
            $invoice->load('user');

            Log::info('Invoice updated', [
                'invoice_id' => $invoice->id
            ]);

            return response()->json([
                'message' => 'Invoice updated successfully',
                'invoice' => $invoice
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating invoice', [
                'invoice_id' => $invoice->id,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to update invoice',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while updating the invoice'
            ], 500);
        }
    }

    /**
     * Remove the specified invoice.
     */
    public function destroy(Invoice $invoice)
    {
        try {
            $invoice->delete();

            Log::info('Invoice deleted', [
                'invoice_id' => $invoice->id
            ]);

            return response()->json([
                'message' => 'Invoice deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting invoice', [
                'invoice_id' => $invoice->id,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to delete invoice',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while deleting the invoice'
            ], 500);
        }
    }
}
