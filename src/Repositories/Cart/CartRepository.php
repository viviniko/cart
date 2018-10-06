<?php

namespace Viviniko\Cart\Repositories\Cart;

interface CartRepository
{
    /**
     * Find data by id
     *
     * @param       $id
     *
     * @return mixed
     */
    public function find($id);

    /**
     * Find data by field and value
     *
     * @param $column
     * @param null $value
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function findAllBy($column, $value = null, $columns = ['*']);

    /**
     * Find data by field and value
     *
     * @param $column
     * @param null $value
     * @param array $columns
     * @return mixed
     */
    public function findBy($column, $value = null, $columns = ['*']);

    /**
     * Save a new entity in repository
     *
     * @param array $data
     *
     * @return mixed
     */
    public function create(array $data);

    /**
     * Update a entity in repository by id
     *
     * @param       $id
     * @param array $data
     *
     * @return mixed
     */
    public function update($id, array $data);

    /**
     * @param $clientId
     * @param array $data
     * @return mixed
     */
    public function updateByClientId($clientId, array $data);

    /**
     * @param $customerId
     * @param array $data
     * @return mixed
     */
    public function updateByCustomerId($customerId, array $data);

    /**
     * Delete a entity in repository by id
     *
     * @param $id
     *
     * @return int
     */
    public function delete($id);

    /**
     * @param array $ids
     * @return mixed
     */
    public function deleteAll(array $ids);

    /**
     * @param $clientId
     * @return mixed
     */
    public function deleteByClientId($clientId);
}