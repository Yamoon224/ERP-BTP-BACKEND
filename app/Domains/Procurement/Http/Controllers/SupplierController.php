<?php

namespace App\Domains\Procurement\Http\Controllers;

use App\Domains\Procurement\Http\Requests\StoreSupplierRequest;
use App\Domains\Procurement\Http\Requests\UpdateSupplierRequest;
use App\Domains\Procurement\Http\Resources\SupplierResource;
use App\Domains\Procurement\Services\SupplierService;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SupplierController extends Controller
{
    public function __construct(private readonly SupplierService $supplierService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SupplierResource::collection($this->supplierService->list(
            $request->only('search', 'is_active', 'sort', 'direction'),
            $request->integer('per_page', 10),
        ));
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $this->supplierService->create($request->validated());

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        return new SupplierResource($this->supplierService->update($supplier, $request->validated()));
    }

    public function destroy(Supplier $supplier): Response
    {
        $this->supplierService->delete($supplier);

        return response()->noContent();
    }
}
