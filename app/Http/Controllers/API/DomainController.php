<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DomainController extends Controller
{
    /**
     * Set domain for the authenticated user
     */
    public function setDomain(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string|max:255|unique:domains,domain'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $domain = Domain::create([
                'user_id' => auth()->id(),
                'domain' => $request->domain,
                'status' => 'active',
                'api_key' => Str::random(32)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Domain set successfully',
                'data' => $domain
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set domain',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get domain for the authenticated user
     */
    public function getDomain()
    {
        try {
            $domain = Domain::where('user_id', auth()->id())->first();

            if (!$domain) {
                return response()->json([
                    'success' => false,
                    'message' => 'No domain found for this user'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $domain
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get domain',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update domain for the authenticated user
     */
    public function updateDomain(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string|max:255|unique:domains,domain,' . auth()->id() . ',user_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $domain = Domain::where('user_id', auth()->id())->first();

            if (!$domain) {
                return response()->json([
                    'success' => false,
                    'message' => 'No domain found for this user'
                ], 404);
            }

            $domain->update([
                'domain' => $request->domain
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Domain updated successfully',
                'data' => $domain
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update domain',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
