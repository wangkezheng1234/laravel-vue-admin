<?php


namespace SmallRuralDog\Admin;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Support\Str;
use SmallRuralDog\Admin\Components\Component;
use SmallRuralDog\Admin\Grid\Actions;
use SmallRuralDog\Admin\Grid\BatchActions;
use SmallRuralDog\Admin\Grid\Column;
use SmallRuralDog\Admin\Grid\Concerns\HasDefaultSort;
use SmallRuralDog\Admin\Grid\Concerns\HasFilter;
use SmallRuralDog\Admin\Grid\Concerns\HasGridAttributes;
use SmallRuralDog\Admin\Grid\Concerns\HasPageAttributes;
use SmallRuralDog\Admin\Grid\Concerns\HasQuickSearch;
use SmallRuralDog\Admin\Grid\Filter;
use SmallRuralDog\Admin\Grid\Model;
use SmallRuralDog\Admin\Grid\Table\Attributes;
use SmallRuralDog\Admin\Grid\Toolbars;
use SmallRuralDog\Admin\Layout\Content;


class Grid extends Component implements \JsonSerializable
{
    use HasGridAttributes, HasPageAttributes, HasDefaultSort, HasQuickSearch, HasFilter;

    /**
     * 组件名称
     * @var string
     */
    protected $componentName = 'Grid';
    /**
     * 组件模型
     * @var Model
     */
    protected $model;
    /**
     * 组件字段
     * @var Column[]
     */
    protected $columns = [];
    protected $rows;
    /**
     * 组件字段属性
     * @var array
     */
    protected $columnAttributes = [];
    /**
     * @var string
     */
    protected $keyName = 'id';
    /**
     * @var bool
     */
    protected $tree = false;
    /**
     * 表格数据来源
     * @var string
     */
    protected $dataUrl;
    protected $isGetData = false;
    private $actions;
    private $batchActions;
    private $toolbars;
    private $top;
    private $bottom;


    public function __construct(Eloquent $model)
    {
        $this->attributes = new Attributes();
        $this->dataUrl = request()->getUri();
        $this->model = new Model($model, $this);
        $this->keyName = $model->getKeyName();
        $this->defaultSort($model->getKeyName(), "asc");
        $this->isGetData = request('get_data') == "true";
        $this->toolbars = new Toolbars();
        $this->batchActions = new BatchActions();
        $this->filter = new Filter($this->model);
    }

    /**
     * 获取自定义数据模型
     * @return Model|Builder
     */
    public function model()
    {
        return $this->model;
    }

    /**
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->keyName;
    }

    /**
     * 自定义数据源路径
     * @param string $dataUrl
     * @return $this
     */
    public function dataUrl(string $dataUrl)
    {
        $this->dataUrl = $dataUrl;
        return $this;
    }

    /**
     * 设置树形表格
     * @param bool $tree
     * @return $this
     */
    public function tree($tree = true)
    {
        $this->tree = $tree;
        return $this;
    }


    /**
     * Grid添加字段
     * @param string $name 对应列内容的字段名
     * @param string $label 显示的标题
     * @param string $columnKey 排序查询等数据操作字段名称
     * @return Column
     */
    public function column($name, $label = '', $columnKey = null)
    {
        if (Str::contains($name, '.')) {
            $this->addRelationColumn($name, $label);
        }

        return $this->addColumn($name, $label, $columnKey);
    }

    /**
     * @param string $name
     * @param string $label
     * @param $columnKey
     * @return Column
     */
    protected function addColumn($name = '', $label = '', $columnKey = null)
    {
        $column = new Column($name, $label, $columnKey);
        $column->setGrid($this);
        $this->columns[] = $column;
        return $column;
    }

    /**
     * Add a relation column to grid.
     *
     * @param string $name
     * @param string $label
     *
     * @return $this|bool|Column
     */
    protected function addRelationColumn($name, $label = '')
    {
        list($relation, $column) = explode('.', $name);

        $model = $this->model()->eloquent();


        if (!method_exists($model, $relation) || !$model->{$relation}() instanceof Relations\Relation) {
        } else {
            $this->model()->with($relation);
        }


    }

    /**
     * @param Column[] $columns
     */
    protected function columns($columns)
    {
        $this->columnAttributes = collect($columns)->map(function (Column $column) {
            return $column->getAttributes();
        })->toArray();
    }

    public function getColumns()
    {
        return $this->columns;
    }

    protected function applyQuery()
    {
        //快捷搜索
        $this->applyQuickSearch();

        $this->applyFilter(false);

    }

    /**
     * 自定义toolbars
     * @param $closure
     * @return $this
     */
    public function toolbars($closure)
    {
        call_user_func($closure, $this->toolbars);
        return $this;
    }

    /**
     * 自定义行操作
     * @param $closure
     * @return $this
     */
    public function actions($closure)
    {
        $this->actions = $closure;
        return $this;
    }

    /**
     * 自定义批量操作
     * @param \Closure $closure
     * @return $this
     */
    public function batchActions(\Closure $closure)
    {
        call_user_func($closure,$this->batchActions);
        return $this;
    }

    /**
     * 获取行操作
     * @param $row
     * @param $key
     * @return mixed
     */
    public function getActions($row, $key)
    {
        $actions = new Actions();
        $actions->row($row)->key($key);
        if ($this->actions) call_user_func($this->actions, $actions);
        return $actions->builderActions();
    }


    /**
     * 隐藏行操作
     * @return $this
     */
    public function hideActions()
    {
        $this->actions->hideActions();
        return $this;
    }

    public function top($closure)
    {
        $this->top = new Content();
        call_user_func($closure, $this->top);
        return $this;
    }

    public function bottom($closure)
    {
        $this->bottom = new Content();
        call_user_func($closure, $this->bottom);
        return $this;
    }

    /**
     * data
     * @return array
     */
    protected function data()
    {

        $this->applyQuery();

        $data = $this->model->buildData();
        return [
            'code' => 200,
            'data' => $data
        ];
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        if (count($this->columnAttributes) <= 0) {
            $this->columns($this->columns);
        }
        if ($this->isGetData) {
            return $this->data();
        } else {
            $viewData['componentName'] = $this->componentName;
            $viewData['routers'] = [
                'resource' => url(request()->getPathInfo())
            ];
            $viewData['keyName'] = $this->keyName;
            $viewData['selection'] = $this->attributes->selection;
            $viewData['tree'] = $this->tree;
            $viewData['defaultSort'] = $this->defaultSort;
            $viewData['columnAttributes'] = $this->columnAttributes;
            $viewData['attributes'] = (array)$this->attributes;
            $viewData['dataUrl'] = $this->dataUrl;
            $viewData['pageSizes'] = $this->pageSizes;
            $viewData['perPage'] = $this->perPage;
            $viewData['pageBackground'] = $this->pageBackground;
            $viewData['toolbars'] = $this->toolbars->builderData();
            $viewData['batchActions'] = $this->batchActions->builderActions();
            $viewData['quickSearch'] = $this->quickSearch;
            $viewData['filter'] = $this->filter->buildFilter();
            $viewData['top'] = $this->top;
            $viewData['bottom'] = $this->bottom;
            return $viewData;
        }
    }
}
