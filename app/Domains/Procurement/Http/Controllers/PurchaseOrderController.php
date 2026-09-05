<?php

namespace App\Domains\Procurement\Http\Controllers;

use App\Domains\Procurement\Http\Requests\StorePurchaseOrderRequest;
use App\Domains\Procurement\Http\Resources\PurchaseOrderResource;
use App\Domains\Procurement\Services\PurchaseOrderService;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrderService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PurchaseOrderResource::collection($this->purchaseOrderService->list(
            $request->only('search', 'supplier_id', 'project_id', 'status', 'sort', 'direction'),
            $request->integer('per_page', 10),
        ));
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrderService->create($request->validated(), $request->user());

        return (new PurchaseOrderResource($purchaseOrder))->response()->setStatusCode(201);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchaseOrderService->find($purchaseOrder->id));
    }
}
