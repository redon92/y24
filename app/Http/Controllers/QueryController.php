<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Request;


class QueryController extends Controller
{

    protected $selectColumns = '';

    protected $commonQueryPart = '';

    protected $edges = [];

    protected $buildArr = [];

    /**
     * main function to test the assignment
     *
     */
    public function query_test()
    {
        $string = file_get_contents(asset('request-data.json'));
        $data = json_decode($string, true);

        $nodes = $data['nodes'];
        $this->edges = $data['edges'];

        $buildQuery = '';
        $buildArr = [];
        foreach ($nodes as $key => $node) {
            $singleQuery = $this->nodeQuery($node, $buildQuery);
            $buildQuery = $singleQuery;
            if ($key==4){
                return $singleQuery;
            }
            $this->buildArr[$node['key']] = $singleQuery;
        }
        return $buildArr;
    }

    public function nodeQuery($node, $queryStr){

        $nodeType = $node['type'];
        switch ($nodeType) {
            case "INPUT":
                if (isset($node['transformObject'])) {
                    $query = $this->query_input($node['transformObject']['tableName'], $node['transformObject']['fields'], $node['key']);
                    return $query;
                } else
                    return 'transformObject is required';
                break;

            case "FILTER":
                if (isset($node['transformObject'])) {
                    $query = $this->query_filter($node['transformObject']['variable_field_name'], $node['transformObject']['joinOperator'], $node['transformObject']['operations'][0], $this->buildArr[$this->findEdgeKey($node['key'])], $node['key']);
                    return $query;
                } else
                    return 'transformObject is required';
                break;

            case "SORT":
                if (isset($node['transformObject'])) {
                    $query = $this->query_sort($node['transformObject'], $this->buildArr[$this->findEdgeKey($node['key'])], $node['key']);
                    return $query;
                } else
                    return 'transformObject is required';
                break;

            case "TEXT_TRANSFORMATION":
                if (isset($node['transformObject'])) {
                    $query = $this->query_text_transformation($node['transformObject'], $this->buildArr[$this->findEdgeKey($node['key'])], $node['key']);
                    return $query;
                } else
                    return 'transformObject is required';
                break;

            case "OUTPUT":
                if (isset($node['transformObject'])) {
                    $query = $this->query_output($node['transformObject']['limit'], $node['transformObject']['offset'], $this->buildArr[$this->findEdgeKey($node['key'])], $node['key']);
                    return $query;
                } else
                    return 'transformObject is required';
            default:
                return $queryStr;
        }
    }

    public function findEdgeKey($nodeKey){
        foreach ($this->edges as $edge){
            if($edge['to']===$nodeKey){
                return $edge['from'];
            }
        }
    }

    public function query_input($tableName, $fields, $nodeKey){
        $concatFields = implode('`, `', $fields);

        $this->selectColumns = $concatFields;
        $this->commonQueryPart = 'SELECT `'.$this->selectColumns.'` ';
        $query = 'SELECT `'.$this->selectColumns.'` FROM '.$tableName.')  as '.$nodeKey.' ';
        return $query;
    }

    public function query_filter($field, $joinOperator, $operation, $edge, $nodeKey){

        $query = '('.$this->commonQueryPart.' FROM ( '.$edge.' WHERE `'.$field.'` '.$operation['operator'].' '.$operation['value'].') as '.$nodeKey;
        return $query;
    }

    public function query_sort($operations, $edge, $nodeKey){
        $sortStart = ' order by ';
        $sortOperations = '';
        foreach ($operations as $key => $operation)
        {
            $semiComma = '';
            if ($key!=0){
                $semiComma = ', ';
            }
            $part = $semiComma.'`'.$operation['target'].'` '.$operation['order'];
            $sortOperations = $sortOperations.$part;
        }
        $query = '('.$this->commonQueryPart.' FROM  '.$edge.' '.$sortStart.$sortOperations.') as '.$nodeKey;

        return $query;
    }

    public function query_text_transformation($transformations, $edge, $nodeKey){
        $transformationStr = $this->commonQueryPart;

        foreach ($transformations as $key => $transformation)
        {
            $transformedColumn = $this->transform_field($transformation['column'], $transformation['transformation']);
            $transformationStr = str_replace('`'.$transformation['column'],$transformedColumn,$transformationStr);
        }

        $query = '('.$transformationStr.' FROM  '.$edge.') as '.$nodeKey;
        return $query;
    }

    public function query_output($limit, $offset, $edge, $nodeKey){

        $query = '(SELECT * FROM '.$edge.' LIMIT '.$offset.', '.$limit.')';
        return $query;
    }

    public function transform_field($field, $transformation){
        $possibleTransformations = ['UPPER', 'LOWER'];
        if (in_array($transformation, $possibleTransformations)){
            return $transformation.'(`'.$field.'`)'.' as `'.$field.'';
        }
    }

}
