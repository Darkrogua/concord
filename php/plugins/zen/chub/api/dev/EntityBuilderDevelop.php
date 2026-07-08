<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\Tools\EntityBuilder;

class EntityBuilderDevelop
{
    # http://axis/chub.api/Dev.EntityBuilderDevelop:create
    public function create()
    {
        EntityBuilder::make()->create(
            'Book',
            'books',
            'Книги',
            'Создать книгу',
            'Изменить книгу'
        );
    }
}