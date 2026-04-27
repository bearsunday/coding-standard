<?php

use Ray\MediaQuery\Annotation\DbQuery;

interface ArticleQueryInterface
{
    // WARNING: type:'row' redundant when return is ?Article
    #[DbQuery('article_item', type: 'row')]
    public function item(int $id): ?Article;

    // WARNING: type:'row_list' redundant when return is array
    #[DbQuery('article_list', type: 'row_list')]
    public function list(): array;

    // OK: nullable array is not a class row
    #[DbQuery('article_ids', type: 'row')]
    public function nullableArray(): ?array;

    // OK: nullable scalar is not a class row
    #[DbQuery('article_count', type: 'row')]
    public function nullableInt(): int|null;

    // OK: no type: argument
    #[DbQuery('article_item')]
    public function itemOk(int $id): ?Article;

    // OK: no type: argument, array return
    #[DbQuery('article_list')]
    public function listOk(): array;
}
