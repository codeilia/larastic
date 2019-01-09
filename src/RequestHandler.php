<?php

namespace Larastic;


use App\Vamyar\ModelNameMapper;
use App\Vamyar\RelationNameMaps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Larastic\Builders\FilterBuilder;

class RequestHandler
{
    public $request;
    public $paginateOffset;
    public $paginateLimit;
    public $filters = [];
    public $relationsFilters = [];
    public $relationMaps;
    public $orders;
    public $idOrder = 'desc';
    public $subResources = [];
    public $parsedRelationFilters = [];
    public $parsedFilters = [];
    public $indexName;

    /**
     * RequestHandler constructor.
     * @param $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
//        $this->paginateOffset = isset($request->cursor) ? base64_decode($request->cursor) : 0;
//        $this->paginateLimit = $request->number ?? 10;
//        $this->paginateOffset = $request->cursor ??  base64_encode(0);
        $this->setPaginateOffset();
        $this->paginateLimit = $request->number ?? 10;
        $this->relationMaps = RelationNameMaps::get();
//        dd($this->relationMaps);
        $this->setIndexName();
        $this->setFilters();
//        $this->setRelationsFilters();
        $this->setOrders();
    }


    /**
     * @param Model $model
     * @return array
     * @internal param Request $request
     */
    public function parseIncludes(Model $model)
    {
        $includes = array_filter(
            explode(',', $this->request->include),
            function ($include) use ($model){
                return (boolean) $model->hasRelation([$include]);
            }
        );

        $this->subResources = $includes;

        return $includes;
    }

    /**
     * @return array
     */
    public function parseFilters()
    {
        $filters = isset($this->request->filter) ? explode(',', $this->request->filter) : [];

        $parsedFilters = [];
        foreach ($filters as $filter) {
            $splittedFilter = $this->splitFilter($filter);

            $parsedFilters[] = [
                'name' => $this->getFilterName($name = snake_case($splittedFilter[0])),
                'delimiter' => $this->getFilterDelimiter($filter),
                'value' => $splittedFilter[1],
                'relation' => $this->getRelationName($name),
            ];
        }

        return $parsedFilters;
    }

    public function parseMustFilters()
    {
        $filters = isset($this->request->must) ? explode(',', $this->request->must) : [];


        $parsedFilters = [];
        foreach ($filters as $filter) {
            $splittedFilter = $this->splitFilter($filter);

            $parsedFilters[] = [
                'name' => $this->getFilterName($name = snake_case($splittedFilter[0])),
                'delimiter' => $this->getFilterDelimiter($filter),
                'value' => $splittedFilter[1],
                'relation' => $this->getRelationName($name),
            ];
        }

        return $parsedFilters;
    }

    public function parseRelationsFilters()
    {
        $filters = $this->parseFilters();

        $filters = array_filter($filters, function ($filter) {
            return !! preg_match('/.+(\.).+/', $filter['name']);
        });

        $relationsFilters = [];

        foreach ($filters as $filter) {
            $temp = explode('.', $filter['name']);

            $relationsFilters[] = [
                'relation' => $temp[0],
                'field' => snake_case($temp[1]),
                'delimiter' => $filter['delimiter'],
                'value' => $filter['value'],
            ];
        }

        $this->parsedRelationFilters = $relationsFilters;

        return $relationsFilters;
    }

    /**
     * @return int|string
     */
    public function getCursor()
    {
        return base64_decode($this->paginateOffset);
//        return isset($this->request->cursor) ? base64_decode($this->request->cursor) : 0;
    }

    /**
     * @return int|mixed
     */
    public function getLimit()
    {
        return $this->paginateLimit;
//        return $this->request->limit ?? 4;
    }

    /**
     * @return array
     */
    public function parseOrders()
    {
        if (isset($this->request->orderBy)) {
            $orders = explode(',', $this->request->orderBy);

            $parsedOrders = [];
            foreach ($orders as $order) {
                $order = explode(':', $order);
                $parsedOrders[] = [
                    'field' => $this->getOrderField(snake_case($order[0])),
                    'order' => $order[1],
                ];
            }

            return $parsedOrders;
        }

//        return [
//            0 => ['field' => 'ladder_at', 'order' => 'desc'],
//        ];

        return [
            0 => ['field' => 'created_at', 'order' => 'desc'],
            1 => ['field' => 'id', 'order' => 'desc']
        ];
    }


    /**
     * @return string
     * @internal param Request $request
     */
    public function getQuery()
    {
        return $this->request->input('query') ?? '*';
    }

    /**
     * @param $filter
     * @return mixed
     */
    private function getFilterDelimiter($filter)
    {
        if (preg_match('/.*(!=).*/', $filter)) {
            return preg_replace("/.*(!=).*/", "$1", $filter);
        }
        return preg_replace("/.*(!=|>|<|=).*/", "$1", $filter);
    }

    public function subResourceRequested($subResource)
    {
        return !! in_array($subResource, $this->subResources);
    }

    /**
     * @param $filter
     * @return array
     */
    private function getOrCondition($filter)
    {
        if (preg_match("/.+\|.+/", $filter)) {
            $dividedFilters =  preg_split( "/(\|)/", $filter);
            $orConditions = [];
            foreach ($dividedFilters as $filter) {
                $dividedFilter = $this->splitFilter($filter);
                $orConditions[$dividedFilter[0]] = [
                    'delimiter' => $this->getFilterDelimiter($filter),
                    'value' => $dividedFilter[1]
                ];
            }

            return $orConditions;
        }
    }


    /**
     * @param $model
     * @return array
     */
    public function getSubResources($model)
    {
        $includes = $this->parseIncludes($model);

        $subResources = [];
        array_walk($includes, function ($include) use(&$subResources, $model) {
            $subResources[$include] = $model->$include;
        });

        return $subResources;
    }

    /**
     * @param Model $model
     * @param FilterBuilder $scoutModel
     * @return FilterBuilder
     */
    public function setIncludes(Model $model, FilterBuilder $scoutModel)
    {
        if (isset($this->request->include)) {
            $includes = $this->parseIncludes($model);
            $scoutModel->with($includes);
        }

        return $scoutModel;
    }

    public function setFilters()
    {
        $filters = $this->parseFilters();
        $mustFilters = $this->parseMustFilters();

//        $relation = 'relation';
//        $filed = 'field';
//        $filterQuery = sprintf('{"nested": {"path": "%s", "query": {"bool": {"must_not": [{"exists": {"field": "%s"}}]}}}}', $relation, $filed);
//        dd(json_decode($filterQuery, true));
//        dd(json_decode("{'nested': {'path': 'j_advert', 'query': {'bool': {'must': [{'match': {'j_advert.verified': false}}]}}}}", true));
//        dd($mustFilters);


        //this is for prevent duplicating relation filters because relation filters has been stored
        // on $relationsFilters field once
//        foreach ($filters as $index => $filter) {
//            if (!!  preg_match('/.+(\.).+/', $filter['name']))
//                unset($filters[$index]);
//        }

        foreach ($filters as $index => $filter) {
            $relation = $filter['relation'];

            if ($this->hasRelationFilter($filter)) {
                if ($filter['delimiter'] == '!='
                    && ($filter['value'] == 'null'
                        || $filter['value'] == 'NULL'
                        || $filter['value'] == null))
                {
//                    $this->filters['bool']['must'][]['nested']['path'] =  $relation;
//                    $this->filters['bool']['must'][]['nested']['query']['bool']['must'][] = [
//                        'exists' => [
//                            'field' => $filter['name']
//                        ]
//                    ];

                    $field = "{$relation}.{$filter['name']}";
                    $filterQuery = sprintf('{"nested": {"path": "%s", "query": {"bool": {"must": [{"exists": {"field": "%s"}}]}}}}', $relation, $field);
//                    $this->filters['bool']['must'][] = json_decode($filterQuery, true);
                    $this->filters['bool']['must'][]['bool'] = json_decode($filterQuery, true);

                    continue;
                }

                if ($filter['delimiter'] == '='
                    && ($filter['value'] == 'null'
                        || $filter['value'] == 'NULL'
                        || $filter['value'] == null))
                {
//                    $this->filters['bool']['must_not'][]['nested']['path'] =  $relation;
//                    $this->filters['bool']['must_not'][]['nested']['query']['bool']['must_not'][] = [
//                        'exists' => [
//                           'filter' => $filter['name']
//                        ]
//                    ];

                    $field = "{$relation}.{$filter['name']}";
                    $filterQuery = sprintf('{"nested": {"path": "%s", "query": {"exists": {"field": "%s"}}}}', $relation, $field);
                    $this->filters['bool']['must_not'][] = json_decode($filterQuery, true);

                    continue;
                }

                if ($filter['delimiter'] == '!=') {
//                    $this->filters['bool']['must_not'][]['nested']['path'] =  $relation;
//                    $this->filters['bool']['must_not'][]['nested']['query']['bool']['must_not'][] = [
//                        'match' => [
//                            $filter['name'] => $filter['value']
//                        ]
//                    ];

                    $field = "{$relation}.{$filter['name']}";
                    $filterQuery = sprintf(
                        '{"nested": {"path": "%s", "query": {"constant_score": {"filter": {"term": {"%s": "%s"}}}}}}',
                        $relation, $field, $filter['value']
                    );
                    $this->filters['bool']['must_not'][] = json_decode($filterQuery);

                    continue;
                }

//                $this->filters['bool']['should'][]['nested']['path'] =  $relation;
//                $this->filters['bool']['should'][]['nested']['query']['bool']['should'][] = $this->getNestedElasticFilter($filter, $relation);

                //TODO: this might have a bug
                $filterQuery = sprintf('{"nested": {"path": "%s", "query": {"bool": {"must": [%s]}}}}', $relation, $this->getNestedElasticFilter($filter, $relation, true));
//                $this->filters['bool']['should'][] = json_decode($filterQuery);
//                should change to this:
//                $this->filters['bool']['must']['bool']['should'][] = json_decode($filterQuery);
                $this->filters['bool']['must'][]['bool']['should'][] = json_decode($filterQuery);
            }
            else {
                if ($filter['delimiter'] == '!='
                    && ($filter['value'] == 'null'
                        || $filter['value'] == 'NULL'
                        || $filter['value'] == null))
                {
                    $this->filters['bool']['must'][] =  [
                        'exists' => [
                            'field' => $filter['name']
                        ]
                    ];

                    continue;
                }

                if ($filter['delimiter'] == '='
                    && ($filter['value'] == 'null'
                        || $filter['value'] == 'NULL'
                        || $filter['value'] == null))
                {
                    $this->filters['bool']['must_not'][] =  [
                        'exists' => [
                            'field' => $filter['name']
                        ]
                    ];

                    continue;
                }

                if ($filter['delimiter'] == '!=') {
                    $this->filters['bool']['must_not'][] =  [
                        'match' => [
                            $filter['name'] => $filter['value']
                        ]
                    ];

                    continue;
                }

//                $this->filters['bool']['should'][] = $this->getElasticFilter($filter);

                //new:
//                $this->filters['bool']['must']['bool']['should'][] = $this->getElasticFilter($filter);
                if ($this->hasBoolShouldKey($this->filters)) {
//                    $currentShould = $this->filters['bool']['must'][0]['bool']['should'];
                    $this->filters['bool']['must'][0]['bool']['should'][] = $this->getElasticFilter($filter);
                    continue;
                }

                $this->filters['bool']['must'][]['bool']['should'][] = $this->getElasticFilter($filter);
            }
        }

//        dd($this->filters['bool']['must']);

//            $this->filters['bool']['should'][] = $this->getElasticFilter($filter);

//        dd($this->request);
        foreach ($mustFilters as $index => $mustFilter) {
            $relation = $mustFilter['relation'];

            if ($this->hasRelationFilter($mustFilter)) {
                if ($mustFilter['delimiter'] == '!='
                    && ($mustFilter['value'] == 'null'
                        || $mustFilter['value'] == 'NULL'
                        || $mustFilter['value'] == null))
                {
//                    $this->filters['bool']['must'][]['nested']['path'] =  $relation;
//                    $this->filters['bool']['must'][]['nested']['query']['bool']['must'][] = [
//                        'exists' => [
//                            'field' => $mustFilter['name']
//                        ]
//                    ];

                    $field = "{$relation}.{$mustFilter['name']}";
                    $mustQuery = sprintf('{"nested": {"path": "%s", "query": {"bool": {"must": [{"exists": {"field": "%s"}}]}}}}', $relation, $field);
                    $this->filters['bool']['must'][] = json_decode($mustQuery, true);

                    continue;
                }

                if ($mustFilter['delimiter'] == '='
                    && ($mustFilter['value'] == 'null'
                        || $mustFilter['value'] == 'NULL'
                        || $mustFilter['value'] == null))
                {
//                    $this->filters['bool']['must_not'][]['nested']['path'] =  $relation;
//                    $this->filters['bool']['must_not'][]['nested']['query']['bool']['must_not'][] = [
//                        'exists' => [
//                            'field' => $mustFilter['name']
//                        ]
//                    ];

//                    $field = "{$relation}.{$mustFilter['name']}";
//                    $mustQuery = sprintf('{"nested": {"path": "%s", "query": {"bool": {"must_not": [{"exists": {"field": "%s"}}]}}}}', $relation, $field);
//                    $this->filters['bool']['must_not'][] = json_decode($mustQuery, true);

                    $field = "{$relation}.{$mustFilter['name']}";
                    $mustQuery = sprintf('{"nested": {"path": "%s", "query": {"exists": {"field": "%s"}}}}', $relation, $field);
                    $this->filters['bool']['must_not'][] = json_decode($mustQuery, true);

                    continue;
                }

                if ($mustFilter['delimiter'] == '!=') {
//                    $this->filters['bool']['must_not'][]['nested']['path'] =  $relation;
//                    $this->filters['bool']['must_not'][]['nested']['query']['bool']['must_not'][] = [
//                        'match' => [
//                            'field' => $mustFilter['name']
//                        ]
//                    ];

//                    $mustQuery = json_decode("{'nested': {'path': '{$relation}', 'query': {'bool': {'must_not': [{'match': {'{$mustFilter['name']}': {$mustFilter['value']}}}]}}}}", true);
//                    $this->filters['bool']['must_not'][] = $mustQuery;

//                    $field = "{$relation}.{$mustFilter['name']}";
//                    $mustQuery = sprintf('{"nested": {"path": "%s", "query": {"bool": {"must_not": [{"match": {"%s": "%s"}}]}}}}', $relation, $field, $mustFilter['value']);
//                    $this->filters['bool']['must_not'][] = json_decode($mustQuery);

                    $field = "{$relation}.{$mustFilter['name']}";
                    $mustQuery = sprintf(
                        '{"nested": {"path": "%s", "query": {"constant_score": {"filter": {"term": {"%s": "%s"}}}}}}',
                        $relation, $field, $mustFilter['value']
                    );
                    $this->filters['bool']['must_not'][] = json_decode($mustQuery);

                    continue;
                }

                $filterQuery = sprintf('{"nested": {"path": "%s", "query": {"bool": {"must": [%s]}}}}', $relation, $this->getNestedElasticFilter($mustFilter, $relation, true));
                $this->filters['bool']['must'][] = json_decode($filterQuery);
            }
            else {
                if ($mustFilter['delimiter'] == '!='
                    && ($mustFilter['value'] == 'null'
                        || $mustFilter['value'] == 'NULL'
                        || $mustFilter['value'] == null))
                {
                    $this->filters['bool']['must'][] =  [
                        'exists' => [
                            'field' => $mustFilter['name']
                        ]
                    ];

                    continue;
                }

                if ($mustFilter['delimiter'] == '='
                    && ($mustFilter['value'] == 'null'
                        || $mustFilter['value'] == 'NULL'
                        || $mustFilter['value'] == null))
                {
                    $this->filters['bool']['must_not'][] =  [
                        'exists' => [
                            'field' => $mustFilter['name']
                        ]
                    ];

                    continue;
                }

                if ($mustFilter['delimiter'] == '!=') {
                    $this->filters['bool']['must_not'][] =  [
                        'match' => [
                            $mustFilter['name'] => $mustFilter['value']
                        ]
                    ];

                    continue;
                }

//                dd($this->getElasticFilter($mustFilter));
                $this->filters['bool']['must'][] = $this->getElasticFilter($mustFilter);
//                dd($this->filters);
            }
        }

        $this->parsedFilters = $filters;
    }

    public function setRelationsFilters()
    {
        $this->relationsFilters = $this->parseRelationsFilters();
    }

    public function getFilters()
    {
        if (empty($this->filters['bool']['should'])
            && empty($this->filters['bool']['must_not'])
            && empty($this->filters['bool']['must'])
        ) {
            $filters = [
                'match_all' => [
                    'boost' => '1.2'
                ],
            ];

            return $filters;
        }

        if (empty($this->filters['bool']['should']))
            unset($this->filters['bool']['should']);

        if (empty($this->filters['bool']['must_not']))
            unset($this->filters['bool']['must_not']);

        if (empty($this->filters['bool']['must']))
            unset($this->filters['bool']['must']);

        return $this->filters;

    }


    public function setOrders()
    {
        $orders = $this->parseOrders();

//        $orders[0]['field'] = preg_replace("/{$order['field']}/", 'j_advertable', $orders[0]['field']);

//        dd($orders);

        foreach ($orders as $index => $order) {
            $this->orders[$index] = [
                $order['field'] => [
                    'order' => $order['order']
                ]
            ];

//            dd($this->isRelationOrder($order));

            if ($this->isRelationOrder($order))
                $this->orders[$index][$order['field']]['nested_path'] = $this->getOrderRelationName($order);
        }

    }

    public function getIdOrder()
    {
        $orders = $this->parseOrders();
        foreach ($orders as $order) {
            if ($order['field'] === 'id')
                return $order['order'];
        }

        return 'desc';
    }

    /**
     * @return int
     */
    public function filterHasOrCondition()
    {
        return preg_match("/.*\|.*/", $this->request->filter);
    }

    /**
     * @param $request
     * @return bool
     */
    public function hasDescOrder($request)
    {
        return isset($request->order) && ($request->order == 'desc' || 'DESC');
    }

    /**
     * @param $filter
     * @return array
     */
    private function splitFilter($filter)
    {
//        $filter = preg_split( "/\|/", $filter);
        return preg_split( "/(=|>|<|!=)/", $filter);
    }

    private function parseOrCondition($subFilters)
    {
        $should = [];

        foreach ($subFilters as $subFilter) {
            $queryValue = $this->getElasticFilter($subFilter);
            $array = $queryValue;
            $queryKey = key($array);
//            dd($queryValue[$queryKey]);
            $should[] = [
                $queryKey => $queryValue[$queryKey]
            ];
        }

//        dd($should);

//        $should = [
//           $this->getElasticFilter($filter)
//        ];
//
//        dd($should);
//
//        if ($this->filterHasOrCondition()) {
//            foreach ($filter['or'] as $k => $or) {
//                $queryValue = $this->getElasticFilter($k, $or);
//                $array = $queryValue;
//                dd($queryKey);
//                $queryKey = key(reset($queryValue));
//                dd($queryKey);
//                $should[$queryKey] = $queryValue;
//            }
//        }

        return $should;
    }

    private function getElasticFilter($filter)
    {
        $query = [];
        $field = $filter['name'];

        switch ($filter['delimiter']) {
            case '=':
                $query['match'] = [
                    $field => $filter['value']
                ];
                break;
            case '>':
                $query['range'] = [
                    $field => [
                        'gte' => $filter['value']
                    ]
                ];
                break;
            case '<':
                $query['range'] = [
                    $field => [
                        'lte' => $filter['value']
                    ]
                ];
                break;
        }

        return $query;
    }

    public function getSearchParams()
    {
        // this is for situation that a middleware filters query! ugly but works :)
        if (empty($this->filters))
            $this->setFilters();
        if (empty($this->relationsFilters))
            $this->setRelationsFilters();
        if (empty($this->orders))
            $this->setOrders();

//        dd($this->paginateOffset);

        return [
            'from' => $this->getCursor(),
            'size' => $this->paginateLimit,
            '_source' => ['id'],
            'sort' => $this->orders,
            'query' => $this->getFilters(),
        ];
    }

    private function splitMustFilter($must)
    {
        return preg_split('/=/', $must);
    }

    private function isRelationFilter($filter)
    {
        if (is_array($filter))
            return array_key_exists("relation", $filter) && $filter['relation'] != null;

        return !! preg_match('/(.+)\.(.+)/', $filter);
    }

    private function hasRelationFilter($filter)
    {
        return $filter['relation'] != null;
    }

    private function getFilterName($filter)
    {
//        if (preg_match('/.+(\.).+/', $name)) {
//            foreach ($this->relationMaps as $key => $value) {
//                if (preg_match('/'. $key . '\.(.*)/', $name)) {
//                    $name = preg_replace(
//                        '/' . $key . '\.(.*)/',
//                        $value . '.$1',
//                        $name
//                    );
//                }
//            }
//        }

        if ($this->isRelationFilter($filter))
            return preg_replace('/(.*)\.(.*)/', '$2', $filter);


        return snake_case($filter);
    }


    private function getRelationName($filter)
    {
        $filterName = is_array($filter) ? $filter['name'] : $filter;

//        dd($this->isRelationFilter($filter));
//        $relationName = preg_replace('/(.*)\.(.*)/', '$1', $filter);
//        dd(array_key_exists($relationName, $this->relationMaps));
        if ($this->isRelationFilter($filter)) {
            $relationName = preg_replace('/(.*)\.(.*)/', '$1', $filterName);
            if (array_key_exists($relationName, $this->relationMaps))
                return $this->relationMaps[$relationName];

            return preg_replace('/(.*)\.(.*)/', '$1', $filterName);
        }

        return null;
    }

//    private function getIndexName($name)
//    {
//        return preg_replace('/(.*)\.(.*)/', '$1', $name);
//    }

    private function getNestedElasticFilter($filter, $relation, $json = true)
    {
        $query = [];
        $field = "{$relation}.{$filter['name']}";

        switch ($filter['delimiter']) {
            case '=':
                $query['match'] = [
                    $field => $filter['value']
                ];
//                $query['path'] = $relation;
                break;

            case '>':
                $query['range'] = [
                    $field => [
                        'gte' => $filter['value']
                    ]
                ];
//                $query['path'] = $relation;
                break;

            case '<':
                $query['range'] = [
                    $field => [
                        'lte' => $filter['value']
                    ]
                ];
//                $query['path'] = $relation;
                break;
        }

        if ($json)
            return json_encode($query);

        return $query;
    }

    private function setIndexName()
    {
//        $controllerClass = $this->request->route()->getAction()['controller'];
//        list($controller, $action) = explode('@', $controllerClass);
//
//        return preg_replace('/(.*)\\\(.*)Controller/', '$2', $controller);

//         $this->indexName = ModelNameMapper::indexName(
//            $this->request->route()->controller->model
//        );

    }

    private function setPaginateOffset()
    {
//        if ($this->request->paginate)
//            return $this->paginateOffset = $this->request->page ?? base64_encode(0);

        return $this->paginateOffset = $this->request->cursor ??  base64_encode(0);
    }

    private function hasBoolShouldKey($filters)
    {
        if (! array_key_exists('bool', $filters))
            return false;

        if (! array_key_exists('must', $filters['bool']))
            return false;

        foreach ($filters['bool']['must'] as $must) {
            if (array_key_exists('bool', $must)) {
                foreach ($must['bool'] as $key => $bool) {
                    if ($key === 'should') {
                        return true;
                    };
                }
            }
        }

        return false;
    }

    private function getOrderField($order)
    {
        if (! preg_match('/(.+)\.(.+)/', $order))
            return $order;

        return $this->getRelationOrder($order);
    }

    private function getRelationOrder($order)
    {
        $splittedOrder = preg_split('/\./', $order);

        $order = preg_replace("/{$splittedOrder[0]}/", $this->relationMaps[$splittedOrder[0]], $order);

//       return $order;
        return $order;
    }

    public function isRelationOrder($order)
    {
        return !! preg_match('/(.+)\.(.+)/', $order['field']);
    }

    public function getOrderRelationName($order)
    {
        $order = preg_split('/\./', $order['field']);

        return $order[0];
    }
}