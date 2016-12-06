<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 26/07/16
 * Time: 19:29
 */

namespace Mindy\Orm\Callback;

use Mindy\Orm\Fields\ManyToManyField;
use Mindy\Orm\Fields\RelatedField;
use Mindy\Orm\Model;
use Mindy\Orm\ModelInterface;
use Mindy\QueryBuilder\LookupBuilder\LookupBuilder;
use Mindy\QueryBuilder\QueryBuilder;

class LookupCallback
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * LookupCallback constructor.
     * @param Model $model
     */
    public function __construct(ModelInterface $model)
    {
        $this->model = $model;
    }

    public function run(QueryBuilder $queryBuilder, LookupBuilder $lookupBuilder, array $lookupNodes, $value)
    {
        $lookup = $lookupBuilder->getDefault();
        $column = '';
        $joinAlias = $queryBuilder->getAlias();

        $ownerModel = $this->model;

        reset($lookupNodes);
        $prevField = $ownerModel->getField(current($lookupNodes));
        if (!$prevField instanceof RelatedField) {
            $prevField = null;
        }

        foreach ($lookupNodes as $i => $node) {

            if ($node == 'through' && $prevField && $prevField instanceof ManyToManyField) {

                $joinAlias = $prevField
                    ->setConnection($ownerModel->getConnection())
                    ->buildThroughQuery($queryBuilder, $queryBuilder->getAlias());

            } else if ($prevField instanceof RelatedField) {

                $relatedModel = $prevField->getRelatedModel();

                /** @var \Mindy\Orm\Fields\RelatedField $field */
                $joinAlias = $prevField
                    ->setConnection($relatedModel->getConnection())
                    ->buildQuery($queryBuilder, $joinAlias);

                if (($nextField = $relatedModel->getField($node)) instanceof RelatedField) {
                    $prevField = $nextField;
                }

            }

            if (count($lookupNodes) == $i + 1) {
                if ($lookupBuilder->hasLookup($node) === false) {
                    $column = $joinAlias . '.' . $lookupBuilder->fetchColumnName($node);
                    $columnWithLookup = $column . $lookupBuilder->getSeparator() . $lookupBuilder->getDefault();
                    $queryBuilder->where([$columnWithLookup => $value]);
                } else {
                    $lookup = $node;
                    $column = $joinAlias . '.' . $lookupBuilder->fetchColumnName($lookupNodes[$i - 1]);
                }
            }
        }

        return [$lookup, $column, $value];
    }
}