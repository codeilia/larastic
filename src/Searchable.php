<?php

namespace Larastic;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable as ScoutSearchable;
use Larastic\Builders\FilterBuilder;
use Larastic\Builders\SearchBuilder;
use \Exception;

trait Searchable
{
    use ScoutSearchable {
        ScoutSearchable::bootSearchable as bootScoutSearchable;
    }

    protected $model;

    /**
     * @var Highlight|null
     */
    private $highlight = null;

    /**
     * @var bool
     */
    private static $isSearchableTraitBooted = false;

    public static function bootSearchable()
    {
        if (self::$isSearchableTraitBooted) {
            return;
        }

        self::bootScoutSearchable();

        self::$isSearchableTraitBooted = true;
    }

    /**
     * @return IndexConfigurator
     * @throws Exception
     */
    public function getIndexConfigurator()
    {
        static $indexConfigurator;

        if (!$indexConfigurator) {
            if (!isset($this->indexConfigurator) || empty($this->indexConfigurator)) {
                throw new Exception(sprintf(
                    'An index configurator for the %s model is not specified.',
                    __CLASS__
                ));
            }

            $indexConfiguratorClass = $this->indexConfigurator;
            $indexConfigurator = new $indexConfiguratorClass;
        }

        return $indexConfigurator;
    }

    /**
     * @return array
     */
    public function getMapping()
    {
        $mapping = $this->mapping ?? [];

        if ($this->usesSoftDelete() && config('scout.soft_delete', false)) {
            array_set($mapping, 'properties.__soft_deleted', ['type' => 'integer']);
        }

        return $mapping;
    }

    /**
     * @return array
     */
    public function getSearchRules()
    {
        return isset($this->searchRules) && count($this->searchRules) > 0 ?
            $this->searchRules : [SearchRule::class];
    }

    /**
     * @param $query
     * @param null $callback
     * @return FilterBuilder|SearchBuilder
     */
    public static function search($query, $callback = null)
    {
        $softDelete = config('scout.soft_delete', false);

        if ($query == '*') {
            return new FilterBuilder(new static, $callback, $softDelete);
        } else {
            return new SearchBuilder(new static, $query, $callback, $softDelete);
        }
    }

    /**
     * @param array $query
     * @return array
     */
    public static function searchRaw(array $query)
    {
        $model = new static();

        return $model->searchableUsing()
            ->searchRaw($model, $query);
    }

    /**
     * @return bool
     */
    public function usesSoftDelete()
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this));
    }

    /**
     * @param Highlight $value
     */
    public function setHighlightAttribute(Highlight $value)
    {
        $this->highlight = $value;
    }

    /**
     * @return Highlight|null
     */
    public function getHighlightAttribute()
    {
        return $this->highlight;
    }

    public static function dynamicSearch($paginate = false)
    {
        $requestHandler = resolve('RequestHandler');

        $query = $requestHandler->getSearchParams();

//        dd(request()->query());

//        dd(json_encode($query));


        $searcResult = static::searchRaw($query);

//        dd($requestHandler->paginateLimit);

//        dd($searcResult);

        $hits = $searcResult['hits']['hits'];

//        dd($hits);

        $ids = [];
        array_walk($hits, function ($hit)  use (&$ids) {
            $ids[] = $hit['_source']['id'];
        });

//        dd($ids);

        $model = new static;

        if (isset($requestHandler->request->include))
            $model = $model->with($requestHandler->parseIncludes($model));

//        dd($ids);
//        dd($model->whereIn('id', $ids)->get());
        $model = $model->whereIn('id', $ids);


//        if ($paginate)
//            static::paginateResult($model, );

//        if (! empty($requestHandler->relationsFilters)) {
////            $model = $model->whereHas($requestHandler->relationsFilters[0]['relation'], function ($query) use ($requestHandler->relationsFilters) {
////                $query->where($requestHandler->relationsFilters[0]['field'], $requestHandler->relationsFilters[0]['delimiter'], $requestHandler->relationsFilters[0]['value']);
////            });
//
//            foreach ($requestHandler->relationsFilters as $relationsFilter) {
////                if ($index == 0) continue;
//
//                $model = $model->orWhereHas($relationsFilter['relation'], function ($query) use ($relationsFilter){
//                    $query->where($relationsFilter['field'], $relationsFilter['delimiter'], $relationsFilter['value']);
//                });
//            }
//        }

        $model = static::setOrders($model, $requestHandler->parseOrders());

        $model->elasticHits = $searcResult['hits'];
        $model->number = $requestHandler->paginateLimit;
        $model->page = $requestHandler->request->page;

        return $model;
    }

    public static function setOrders($model, $orders)
    {
//        dd($orders);
//        foreach ($orders as $order) {
//            $model->orderBy($order['field'], $order['order']);
//        }
//
//        return $model;


        foreach ($orders as $order) {
            if (static::isRelationOrder($order)) {

                $relation = static::getOrderRelationName($order);

//                dd($order);

                $field = static::getOrderRelationField($order);

                $model->orderBy("{$relation}->{$field}", $order['order']);
            }
            else
                $model->orderBy($order['field'], $order['order']);
        }

        return $model;
    }

//    public static function isRelationOrder($order)
//    {
//        return !! preg_match('/(.+)\.(.+)/', $order['field']);
//    }
//
//    public static function getOrderRelationName($order)
//    {
//        $order = preg_split('/\./', $order['field']);
//
//        return static::mapOrderName($order[0]);
//    }
}
