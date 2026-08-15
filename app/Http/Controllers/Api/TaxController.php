<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Models\TaxRate;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    use MapsCamelCaseFields;
    public function getTaxRates(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $rates = TaxRate::orderBy('priority')->orderBy('name')->paginate($perPage);
        return response()->json(['success' => true, 'data' => $rates]);
    }

    public function getTaxRate(string $id): JsonResponse
    {
        try {
            $rate = TaxRate::findOrFail($id);
            return response()->json(['success' => true, 'data' => $rate]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function createTaxRate(Request $request): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'isActive' => 'is_active',
                'zipPattern' => 'zip_pattern',
            ]);
            $request->replace($input);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'rate' => 'required|numeric|min:0|max:100',
                'type' => 'required|in:PERCENTAGE,FIXED',
                'country' => 'nullable|string|size:2',
                'state' => 'nullable|string|max:100',
                'city' => 'nullable|string|max:100',
                'zip_pattern' => 'nullable|string|max:20',
                'is_active' => 'boolean',
                'priority' => 'integer|min:0',
                'description' => 'nullable|string',
            ]);
            $rate = TaxRate::create($validated);
            return response()->json(['success' => true, 'message' => 'Tax rate created', 'data' => $rate], 201);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function updateTaxRate(Request $request, string $id): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'isActive' => 'is_active',
                'zipPattern' => 'zip_pattern',
            ]);

            $rate = TaxRate::findOrFail($id);
            $rate->update(collect($input)->only([
                'name', 'rate', 'type', 'country', 'state', 'city', 'zip_pattern', 'is_active', 'priority', 'description'
            ])->toArray());
            return response()->json(['success' => true, 'data' => $rate]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function deleteTaxRate(string $id): JsonResponse
    {
        try {
            $rate = TaxRate::findOrFail($id);
            $rate->delete();
            return response()->json(['success' => true, 'message' => 'Tax rate deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    public function calculateTax(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['subtotal' => 'required|numeric|min:0', 'country' => 'nullable|string|size:2', 'state' => 'nullable|string|max:100', 'city' => 'nullable|string|max:100']);
            $query = TaxRate::where('is_active', true);
            if (!empty($validated['country'])) { $query->where(function ($q) use ($validated) { $q->where('country', $validated['country'])->orWhereNull('country'); }); }
            if (!empty($validated['state'])) { $query->where(function ($q) use ($validated) { $q->where('state', $validated['state'])->orWhereNull('state'); }); }
            $rates = $query->orderBy('priority')->get();
            $taxAmount = 0;
            $breakdown = [];
            foreach ($rates as $rate) {
                $amount = $rate->type === 'PERCENTAGE' ? ($validated['subtotal'] * $rate->rate / 100) : $rate->rate;
                $taxAmount += $amount;
                $breakdown[] = ['name' => $rate->name, 'rate' => $rate->rate, 'type' => $rate->type, 'amount' => round($amount, 2)];
            }
            return response()->json(['success' => true, 'data' => ['subtotal' => $validated['subtotal'], 'tax_amount' => round($taxAmount, 2), 'total' => round($validated['subtotal'] + $taxAmount, 2), 'breakdown' => $breakdown]]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); }
    }
}
