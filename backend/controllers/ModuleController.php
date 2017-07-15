<?php
/**
 * file: ModuleController.php
 * Desc: 模块生成
 * user: liujx
 * date: 2016-07-20
 */

// 引入命名空间
namespace backend\controllers;

use common\helpers\Helper;
use Yii;
use backend\models\Menu;
use backend\models\Auth;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/**
 * Class ModuleController 模块生成测试文件
 * @package backend\controllers
 */
class ModuleController extends Controller
{
    /**
     * actionIndex() 首页显示
     * @return string
     */
    public function actionIndex()
    {
        // 查询到库里面全部的表
        $tables = Yii::$app->db->getSchema()->getTableSchemas();
        $tables = ArrayHelper::map($tables, 'name', 'name');
        return $this->render('index', [
            'tables' => $tables,
        ]);
    }

    /**
     * actionCreate() 第一步接收标题和数据表数据生成表单配置信息
     * @return mixed|string
     */
    public function actionCreate()
    {
        // 接收参数
        $request = Yii::$app->request;
        if ($request->isAjax) {
            $strTitle = $request->post('title'); // 标题
            $strTable = $request->post('table'); // 数据库表
            if ( ! empty($strTable) && ! empty($strTitle)) {
                // 获取表信息
                $db = Yii::$app->db;
                $this->arrJson['errCode'] = 217;
                $tables = $db->createCommand('SHOW TABLES')->queryAll();
                if ($tables) {
                    $isHave = false;
                    foreach ($tables as $value) {
                        if (in_array($strTable, $value)) {
                            $isHave = true;
                            break;
                        }
                    }

                    if ($isHave) {
                        // 查询表结构信息
                        $arrTables = $db->createCommand('SHOW FULL COLUMNS FROM `'.$strTable.'`')->queryAll();
                        $this->arrJson['errCode'] = 218;
                        if ($arrTables) $this->handleJson($this->createForm($arrTables));
                    }
                }
            }
        }

        return $this->returnJson();
    }

    /**
     * actionUpdate() 第二步生成预览HTML文件
     * @return mixed|string
     */
    public function actionUpdate()
    {
        $request = Yii::$app->request;
        if ($request->isAjax) {
            $attr  = $request->post('attr');
            $table = $request->post('table');
            if ($attr) {
                $this->arrJson['errCode'] = 217;
                if ($table && ($name = ltrim($table, Yii::$app->db->tablePrefix))) {
                    // 拼接字符串
                    $dirName  = Yii::$app->basePath.'/';
                    $strCName = Helper::strToUpperWords($name).'Controller.php';
                    $strVName = 'index.php';
                    $strVPath = $dirName.'views/'.str_replace('_', '-', $name).'/';     // 视图目录

                    // 生成目录
                    if ( ! file_exists($strVPath)) mkdir($strVPath, 644, true);

                    // 返回数据
                    $this->handleJson([
                        'html'       => highlight_string($this->createPHP($attr, $request->post('title')), true),
                        'file'       => [$strVName, file_exists($strVPath . $strVName)],
                        'controller' => [$strCName, file_exists($dirName . 'Controllers/'.$strCName)],
                    ]);
                }
            }
        }

        return $this->returnJson();
    }

    /**
     * actionProduce() 第三步开始生成文件
     * @return mixed|string
     */
    public function actionProduce()
    {
        $request = Yii::$app->request;
        if ($request->isAjax) {
            // 接收参数
            $attr  = $request->post('attr');       // 表单信息
            $table = $request->post('table');      // 操作表
            $title = $request->post('title');      // 标题信息
            $html  = $request->post('html');       // HTML 文件名
            $php   = $request->post('controller'); // PHP  文件名
            $auth  = (int)$request->post('auth');  // 生成权限
            $menu  = (int)$request->post('menu');  // 生成导航
            $allow = (int)$request->post('allow'); // 允许文件覆盖

            if ($attr && $table && $title && $html && $php) {
                $this->arrJson['errCode'] = 217;
                if ($table && ($name = trim($table, Yii::$app->db->tablePrefix))) {
                    // 试图文件目录、导航栏目、权限名称使用字符串
                    $strName = str_replace('_', '-', $name);

                    // 拼接字符串
                    $dirName  = Yii::$app->basePath.'/';
                    $strCName = $dirName.'Controllers/'.(stripos(Helper::strToUpperWords($php), '.php') ? $php : $php.'.php');
                    $strVName = $dirName.'views/'.$strName.'/'.(stripos($html, '.php') ? $html : $html.'.php');


                    // 验证文件不存在
                    $this->arrJson['errCode'] = 219;
                    if ($allow === 1 ||  (!file_exists($strCName) && !file_exists($strVName))) {
                        // 生成权限
                        if ($auth == 1) $this->createAuth($strName, $title);

                        // 生成导航栏目
                        if ($menu == 1) $this->createMenu($strName, $title);

                        // 生成视图文件
                        $strWhere = $this->createPHP($attr, $title, $strVName);

                        // 生成控制器
                        $this->createController($name, $title, $strCName, $strWhere);

                        // 返回数据
                        $this->handleJson(Url::toRoute([$name.'/index']));
                    }
                }
            }
        }

        return $this->returnJson();
    }

    /**
     * createAuth()生成权限操作
     * @access private
     * @param  string $prefix 前缀名称
     * @param  string $title  标题
     * @return void
     */
    private function createAuth($prefix, $title)
    {
        $strPrefix = trim($prefix, '/').'/';
        $arrAuth   = [
            'index'  => '显示',
            'search' => '搜索',
            'create' => '创建',
            'update' => '修改',
            'delete' => '删除',
            'export' => '导出'
        ];

        foreach ($arrAuth as $key => $value) {
            $model = new Auth();
            $model->name = $model->newName =  $strPrefix.$key;
            $model->type = Auth::TYPE_PERMISSION;
            $model->description = $value.$title;
            $model->save();
        }
    }

    /**
     * createMenu() 生成导航栏信息
     * @access private
     * @param  string $name  权限名称
     * @param  string $title 导航栏目标题
     * @return void
     */
    private function createMenu($name, $title)
    {
        if ( ! Menu::find()->where(['menu_name' => $title])->one()) {
            $model = new Menu();
            $model->menu_name   = $title;
            $model->pid         = 0;
            $model->icons       = 'icon-cog';
            $model->url         = $name.'/index';
            $model->status      = 1;
            $model->save(false);
        }
    }

    /**
     * 生成视图文件信息
     * @param $array
     * @return string
     */
    private function createForm($array)
    {
        $strHtml = '<div class="alert alert-info">
    <button data-dismiss="alert" class="close" type="button">×</button>
    <strong>填写配置表格信息!</strong>
</div>';
        foreach ($array as $value) {
            $key     = $value['Field'];
            $sTitle  = isset($value['Comment']) && ! empty($value['Comment']) ? $value['Comment'] : $value['Field'];
            $sOption = isset($value['Null']) && $value['Null'] == 'NO' ? '"required": true,' : '';
            if (stripos($value['Type'], 'int(') !== false) $sOption .= '"number": true,';
            if (stripos($value['Type'], 'varchar(') !== false) {
                $sLen = trim(str_replace('varchar(', '', $value['Type']), ')');
                $sOption .= '"rangelength": "[2, '.$sLen.']"';
            }

            $sOther = stripos($value['Field'], '_at') !== false ? 'meTables.dateTimeString' : '';

            $strHtml .= <<<HTML
<div class="alert alert-success me-alert-su">
    <span class="label label-success me-label-sp">{$key}</span>
    <label class="me-label">标题: <input type="text" name="attr[{$key}][title]" value="{$sTitle}" required="true" /></label>
    <label class="me-label">编辑：
        <select class="is-hide" name="attr[{$key}][edit]">
            <option value="1" selected="selected">开启</option>
            <option value="0" >关闭</option>
        </select>
        <select name="attr[{$key}][type]">
            <option value="text" selected="selected">text</option>
            <option value="hidden">hidden</option>
            <option value="select">select</option>
            <option value="radio">radio</option>
            <option value="password">password</option>
            <option value="textarea">textarea</option>
        </select>
        <input type="text" name="attr[{$key}][options]" value='{$sOption}'/>
    </label>
    <label class="me-label">搜索：
        <select name="attr[{$key}][search]">
            <option value="1">开启</option>
            <option value="0" selected="selected">关闭</option>
        </select>
    </label>
    <label class="me-label">排序：<select name="attr[{$key}][bSortable]">
        <option value="1" >开启</option>
        <option value="0" selected="selected">关闭</option>
    </select></label>
    <label class="me-label">回调：<input type="text" name="attr[{$key}][createdCell]" value="{$sOther}" /></label>
</div>
HTML;
        }

        return $strHtml;
    }

    /**
     * createHtml() 生成预览HTML文件
     * @access private
     * @param  array  $array 接收表单配置文件
     * @param  string $title 标题信息
     * @param  string $path  文件地址
     * @return string 返回 字符串
     */
    private function createPHP($array, $title, $path = '')
    {
        $strHtml = $strWhere =  '';
        if ($array) {
            foreach ($array as $key => $value) {
                $html = "\t\t\t{\"title\": \"{$value['title']}\", \"data\": \"{$key}\", \"sName\": \"{$key}\", ";

                // 编辑
                if ($value['edit'] == 1) $html .= "\"edit\": {\"type\": \"{$value['type']}\", " . trim($value['options'], ',') . "}, ";

                // 搜索
                if ($value['search'] == 1) {
                    $html     .= "\"search\": {\"type\": \"text\"}, ";
                    $strWhere .= "\t\t\t'{$key}' => '=', \n";
                }

                // 排序
                if ($value['bSortable'] == 0) $html .= '"bSortable": false, ';

                // 回调
                if (!empty($value['createdCell'])) $html .= '"createdCell" : '.$value['createdCell'].', ';

                $strHtml .= trim($html, ', ')."}, \n";
            }
        }

        $sHtml =  <<<html
<?php
// 定义标题和面包屑信息
\$this->title = '{$title}';
?>
<!-- 表格按钮 -->
<p id="me-table-buttons"></p>
<!-- 表格数据 -->
<table class="table table-striped table-bordered table-hover" id="show-table"></table>

<?php \$this->beginBlock('javascript') ?>
<script type="text/javascript">
    var m = meTables({
        title: "{$title}",
        table: {
            "aoColumns": [
                {$strHtml}
            ]       
        }
    });
    
    /**
    meTables.fn.extend({
        // 显示的前置和后置操作
        beforeShow: function(data, child) {
            return true;
        },
        afterShow: function(data, child) {
            return true;
        },
        
        // 编辑的前置和后置操作
        beforeSave: function(data, child) {
            return true;
        },
        afterSave: function(data, child) {
            return true;
        }
    });
    */

     \$(function(){
         m.init();
     });
</script>
<?php \$this->endBlock(); ?>
html;
        // 生成文件
        if ( ! empty($path)) {
            $dirName = dirname($path);
            if (!file_exists($dirName)) mkdir($dirName, 0755, true);
            file_put_contents($path, $sHtml);
            return $strWhere;
        }

        return $sHtml;
    }

    /**
     * createController()生成控制器文件
     * @access private
     * @param  string $name  控制器名
     * @param  string $title 标题
     * @param  string $path  文件名
     * @param  string $where 查询条件
     * @return void
     */
    private function createController($name, $title, $path, $where)
    {
        $strFile  = trim(strrchr($path, '/'), '/');
        $strDate  = date('Y-m-d H:i:s');
        $strName  = trim($strFile, '.class.php');
        $strModel = Helper::strToUpperWords($name);
        $strControllers = <<<Html
<?php
namespace backend\controllers;

/**
 * Class {$strName} {$title} 执行操作控制器
 * @package backend\controllers
 */
class {$strName} extends Controller
{
    /**
     * @var string 定义使用的model
     */
    public \$modelClass = 'backend\models\\{$strModel}';
     
    /**
     * where() 查询处理
     * @param  array \$params
     * @return array 返回数组
     */
    public function where(\$params)
    {
        return [
            {$where}
        ];
    }
}

Html;

        file_put_contents($path, $strControllers);
    }
}