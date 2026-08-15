<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AdminService;
use Illuminate\Http\Request;

/**
 * @deprecated Blade admin views are no longer used (admin is React SPA).
 *             This controller is kept for reference only.
 */
class AdminOrderController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    // ── Orders ──
    public function ordersIndex(Request $request)
    {
        $orders = $this->adminService->getOrders($request->only(['status', 'search']));
        return view('admin.orders.index', compact('orders'));
    }

    public function ordersShow(string $id)
    {
        $order = $this->adminService->getOrderModel($id);
        return view('admin.orders.show', compact('order'));
    }

    public function ordersUpdateStatus(Request $request, string $id)
    {
        $request->validate(['status' => 'required|string']);
        $this->adminService->updateOrderStatus($id, $request->status);
        return redirect()->route('admin.orders.show', $id)
            ->with('success', "Order status updated to {$request->status}");
    }

    // ── Abandoned Carts ──
    public function abandonedCartsIndex()
    {
        $carts = $this->adminService->getAbandonedCarts();
        $stats = $this->adminService->getAbandonedCartStats();
        return view('admin.abandoned-carts.index', compact('carts', 'stats'));
    }

    public function abandonedCartsShow(string $id)
    {
        $cart = $this->adminService->getAbandonedCartModel($id);
        return view('admin.abandoned-carts.show', compact('cart'));
    }

    // ── Inventory ──
    public function inventoryIndex()
    {
        $inventory = $this->adminService->getInventory();
        return view('admin.inventory.index', compact('inventory'));
    }

    public function inventoryShow(string $productId)
    {
        $inventory = $this->adminService->getInventoryByProduct($productId);
        abort_unless($inventory, 404, 'Inventory not found');
        $movement = $this->adminService->getInventoryMovement($productId);
        return view('admin.inventory.show', compact('inventory', 'movement'));
    }

    public function inventoryAddStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|string|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        $this->adminService->addStock($validated['product_id'], $validated['quantity'], $validated['reason'] ?? '');
        return redirect()->route('admin.inventory.index')
            ->with('success', 'Stock added successfully');
    }

    public function inventoryReduceStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|string|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        try {
            $this->adminService->reduceStock($validated['product_id'], $validated['quantity'], $validated['reason'] ?? '');
            return redirect()->route('admin.inventory.index')
                ->with('success', 'Stock reduced successfully');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function inventoryMovement(string $productId)
    {
        $movement = $this->adminService->getInventoryMovement($productId);
        $product = Product::find($productId);
        abort_unless($product, 404, 'Product not found');
        return view('admin.inventory.movement', compact('movement', 'product'));
    }
}
