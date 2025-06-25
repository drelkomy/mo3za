<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FinancialDetailResource;
use App\Models\FinancialDetail;
use Illuminate\Http\Request;

class FinancialDetailController extends Controller
{
    /**
     * Display the financial details for the authenticated user.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Fetch the single financial detail record from the database to display to all authenticated users
        // This ensures the same data is shown to everyone, modifiable only by admins
        $financialDetail = FinancialDetail::first();
        
        if (!$financialDetail) {
            return response()->json([
                'message' => 'No financial details found in the database. Please ensure the data is configured by an admin for all users to see.'
            ], 404);
        }

        return new FinancialDetailResource($financialDetail);
    }
}
