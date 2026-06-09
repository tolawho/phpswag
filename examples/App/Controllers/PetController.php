<?php

namespace App\Controllers;

class PetController
{
    /**
     * @route POST /pet/{petId}/uploadImage
     * @summary Uploads an image.
     * @description Upload image of the pet.
     * @operationId uploadFile
     * @tag pet
     * @accept application/octet-stream
     * @produce json
     * @path int $petId ID of pet to update format(int64)
     * @query string $additionalMetadata Additional Metadata
     * @body string file type(string) format(binary)
     * @response 200 \App\Models\ApiResponse successful operation
     * @response 400 void No file uploaded
     * @response 404 void Pet not found
     * @response default void Unexpected error
     * @security petstore_auth[write:pets, read:pets]
     */
    public function uploadFile(int $petId)
    {
    }

    /**
     * @route POST /pet
     * @summary Add a new pet to the store.
     * @description Add a new pet to the store.
     * @operationId addPet
     * @tag pet
     * @accept json, xml, x-www-form-urlencoded
     * @produce json, xml
     * @body \App\Models\Pet Create a new pet in the store
     * @response 200 \App\Models\Pet Successful operation
     * @response 400 void Invalid input
     * @response 422 void Validation exception
     * @response default void Unexpected error
     * @security petstore_auth[write:pets, read:pets]
     */
    public function addPet()
    {
    }

    /**
     * @route PUT /pet
     * @summary Update an existing pet.
     * @description Update an existing pet by Id.
     * @operationId updatePet
     * @tag pet
     * @accept json, xml, x-www-form-urlencoded
     * @produce json, xml
     * @body \App\Models\Pet Update an existent pet in the store
     * @response 200 \App\Models\Pet Successful operation
     * @response 400 void Invalid ID supplied
     * @response 404 void Pet not found
     * @response 422 void Validation exception
     * @response default void Unexpected error
     * @security petstore_auth[write:pets, read:pets]
     */
    public function updatePet()
    {
    }

    /**
     * @route GET /pet/findByStatus
     * @summary Finds Pets by status.
     * @description Multiple status values can be provided with comma separated strings.
     * @operationId findPetsByStatus
     * @tag pet
     * @produce json, xml
     * @query string $status Status values that need to be considered for filter enum(available,pending,sold) default(available)
     * @response 200 \App\Models\Pet[] successful operation
     * @response 400 void Invalid status value
     * @response default void Unexpected error
     * @security petstore_auth[write:pets, read:pets]
     */
    public function findPetsByStatus()
    {
    }

    /**
     * @route GET /pet/findByTags
     * @summary Finds Pets by tags.
     * @description Multiple tags can be provided with comma separated strings. Use tag1, tag2, tag3 for testing.
     * @operationId findPetsByTags
     * @tag pet
     * @produce json, xml
     * @query string[] $tags Tags to filter by
     * @response 200 \App\Models\Pet[] successful operation
     * @response 400 void Invalid tag value
     * @response default void Unexpected error
     * @security petstore_auth[write:pets, read:pets]
     */
    public function findPetsByTags()
    {
    }

    /**
     * @route GET /pet/{petId}
     * @summary Find pet by ID.
     * @description Returns a single pet.
     * @operationId getPetById
     * @tag pet
     * @produce json, xml
     * @path int $petId ID of pet to return format(int64)
     * @response 200 \App\Models\Pet successful operation
     * @response 400 void Invalid ID supplied
     * @response 404 void Pet not found
     * @response default void Unexpected error
     * @security api_key
     * @security petstore_auth[write:pets, read:pets]
     */
    public function getPetById(int $petId)
    {
    }

    /**
     * @route POST /pet/{petId}
     * @summary Updates a pet in the store with form data.
     * @description Updates a pet resource based on the form data.
     * @operationId updatePetWithForm
     * @tag pet
     * @produce json, xml
     * @path int $petId ID of pet that needs to be updated format(int64)
     * @query string $name Name of pet that needs to be updated
     * @query string $status Status of pet that needs to be updated
     * @response 200 \App\Models\Pet successful operation
     * @response 400 void Invalid input
     * @response default void Unexpected error
     * @security petstore_auth[write:pets, read:pets]
     */
    public function updatePetWithForm(int $petId)
    {
    }

    /**
     * @route DELETE /pet/{petId}
     * @summary Deletes a pet.
     * @description Delete a pet.
     * @operationId deletePet
     * @tag pet
     * @produce json, xml
     * @header string $api_key
     * @path int $petId Pet id to delete format(int64)
     * @response 200 void Pet deleted
     * @response 400 void Invalid pet value
     * @response default void Unexpected error
     * @security petstore_auth[write:pets, read:pets]
     */
    public function deletePet(int $petId)
    {
    }
}
