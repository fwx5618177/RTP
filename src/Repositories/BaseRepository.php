<?php

namespace App\Repositories;

interface BaseRepository
{
    public function find(int $id);
    public function create($entity);
    public function update($entity);
    public function delete(int $id);
}
