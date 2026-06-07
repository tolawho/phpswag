<?php

namespace App\Controllers;

class StoreController
{
    /**
     * @route GET /store/inventory
     * @summary Returns pet inventories by status.
     * @description Returns a map of status codes to quantities.
     * @operationId getInventory
     * @tag store
     * @produce json
     * @response 200 array<string, int> successful operation
     * @response default void Unexpected error
     * @security api_key
     */
    public function getInventory()
    {
    }

    /**
     * @route POST /store/order
     * @summary Place an order for a pet.
     * @description Place a new order in the store.
     * @operationId placeOrder
     * @tag store
     * @accept json, xml, x-www-form-urlencoded
     * @produce json, xml
     * @body \App\Models\Order order placed for purchasing the pet
     * @response 200 \App\Models\Order successful operation
     * @response 400 void Invalid input
     * @response 422 void Validation exception
     * @response default void Unexpected error
     */
    public function placeOrder()
    {
    }

    /**
     * @route GET /store/order/{orderId}
     * @summary Find purchase order by ID.
     * @description For valid response try integer IDs with value <= 5 or > 10. Other values will generate exceptions.
     * @operationId getOrderById
     * @tag store
     * @produce json, xml
     * @path int $orderId ID of order that needs to be fetched format(int64)
     * @response 200 \App\Models\Order successful operation
     * @response 400 void Invalid ID supplied
     * @response 404 void Order not found
     * @response default void Unexpected error
     */
    public function getOrderById(int $orderId)
    {
    }

    /**
     * @route DELETE /store/order/{orderId}
     * @summary Delete purchase order by identifier.
     * @description For valid response try integer IDs with value < 1000. Anything above 1000 or non-integers will generate API errors.
     * @operationId deleteOrder
     * @tag store
     * @produce json, xml
     * @path int $orderId ID of the order that needs to be deleted format(int64)
     * @response 200 void order deleted
     * @response 400 void Invalid ID supplied
     * @response 404 void Order not found
     * @response default void Unexpected error
     */
    public function deleteOrder(int $orderId)
    {
    }
}
