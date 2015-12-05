<?php
namespace futuretek\form\dynamicform;

use futuretek\shared\dom\AdvancedHtmlDom;
use Yii;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelector;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use yii\helpers\Html;
use yii\base\InvalidConfigException;
use yii\web\View;

/**
 * yii2-dynamicform is widget to yii2 framework to clone form elements in a nested manner, maintaining accessibility.
 *
 * @author Petr Leo Compel <petr.compel@futuretek.cz>
 */
class DynamicFormWidget extends \yii\base\Widget
{
    const WIDGET_NAME = 'dynamicform';

    /**
     * @var bool
     */
    public $preloadedModels = false;
    /**
     * @var string
     */
    public $deleteButton;
    /**
     * @var array fields to be validated.
     */
    public $formFields = [];
    /**
     * @var string form ID
     */
    public $formId;
    /**
     * @var string
     */
    public $insertButton;
    /**
     * @var string 'bottom' or 'top';
     */
    public $insertPosition = 'bottom';
    /**
     * @var string
     */
    public $limit = 999;
    /**
     * @var integer
     */
    public $min = 1;
     /**
     * @var Model|ActiveRecord the model used for the form
     */
    public $model;
    /**
     * @var string
     */
    public $widgetBody;
    /**
     * @var string
     */
    public $widgetContainer;
    /**
     * @var string
     */
    public $widgetItem;

    /**
     * @var string
     */
    private $_options;
    /**
     * @var string
     */
    private $_insertPositions = ['bottom', 'top'];
    /**
     * @var string the hashed global variable name storing the pluginOptions.
     */
    private $_hashVar;
    /**
     * @var string the Json encoded options.
     */
    private $_encodedOptions = '';

    /**
     * Initializes the widget.
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if ($this->widgetContainer === null || (preg_match('/^\w+$/', $this->widgetContainer) === 0)) {
            throw new InvalidConfigException('Invalid configuration to property "widgetContainer". 
                Allowed only alphanumeric characters plus underline: [A-Za-z0-9_]');
        }
        if ($this->widgetBody === null) {
            throw new InvalidConfigException("The 'widgetBody' property must be set.");
        }
        if ($this->widgetItem === null) {
            throw new InvalidConfigException("The 'widgetItem' property must be set.");
        }
        if ($this->model === null || !$this->model instanceof Model) {
            throw new InvalidConfigException("The 'model' property must be set and must extend from '\\yii\\base\\Model'.");
        }
        if ($this->formId === null) {
            throw new InvalidConfigException("The 'formId' property must be set.");
        }
        if ($this->insertPosition === null || ! in_array($this->insertPosition, $this->_insertPositions, false)) {
            throw new InvalidConfigException("Invalid configuration to property 'insertPosition' (allowed values: 'bottom' or 'top')");
        }
        if (!is_array($this->formFields) || count($this->formFields) === 0) {
            throw new InvalidConfigException("The 'formFields' property must be set.");
        }

        $this->initOptions();
    }

    /**
     * Initializes the widget options.
     */
    protected function initOptions()
    {

        $this->_options['preloadedModels']  = $this->preloadedModels;
        $this->_options['deleteButton']     = $this->deleteButton;
        $this->_options['fields']           = [];
        $this->_options['formId']           = $this->formId;
        $this->_options['insertButton']     = $this->insertButton;
        $this->_options['insertPosition']   = $this->insertPosition;
        $this->_options['limit']            = $this->limit;
        $this->_options['min']              = $this->min;
        $this->_options['widgetBody']       = $this->widgetBody;
        $this->_options['widgetContainer']  = $this->widgetContainer;
        $this->_options['widgetItem']       = $this->widgetItem;

        foreach ($this->formFields as $field) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->_options['fields'][] = [
                'id' => Html::getInputId($this->model, '[{}]' . $field),
                'name' => Html::getInputName($this->model, '[{}]' . $field)
            ];
        }

        ob_start();
        ob_implicit_flush(false);
    }

    /**
     * Registers plugin options by storing it in a hashed javascript variable.
     *
     * @param View $view The View object
     */
    protected function registerOptions($view)
    {
        $view->registerJs("var {$this->_hashVar} = {$this->_encodedOptions};\n", $view::POS_HEAD);
    }

    /**
     * Generates a hashed variable to store the options.
     */
    protected function hashOptions()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $this->_encodedOptions = Json::encode($this->_options);
        $this->_hashVar = self::WIDGET_NAME . '_' . hash('crc32', $this->_encodedOptions);
    }

    /**
     * Returns the hashed variable.
     *
     * @return string
     */
    protected function getHashVarName()
    {
        if (isset(Yii::$app->params[self::WIDGET_NAME][$this->widgetContainer])) {
            return Yii::$app->params[self::WIDGET_NAME][$this->widgetContainer];
        }

        return $this->_hashVar;
    }

    /**
     * Register the actual widget.
     *
     * @return boolean
     */
    public function registerHashVarWidget()
    {
        if (!isset(Yii::$app->params[self::WIDGET_NAME][$this->widgetContainer])) {
            Yii::$app->params[self::WIDGET_NAME][$this->widgetContainer] = $this->_hashVar;
            return true;
        }

        return false;
    }

    /**
     * Registers the needed assets.
     *
     * @param View $view The View object
     */
    public function registerAssets($view)
    {
        DynamicFormAsset::register($view);

        // add a click handler for the clone button
        $js = 'jQuery("#' . $this->formId . '").on("click", "' . $this->insertButton . '", function(e) {'. "\n";
        $js .= "    e.preventDefault();\n";
        $js .= '    jQuery(".' .  $this->widgetContainer . '").triggerHandler("beforeInsert", [jQuery(this)]);' . "\n";
        $js .= '    jQuery(".' .  $this->widgetContainer . '").yiiDynamicForm("addItem", '. $this->_hashVar . ", e, jQuery(this));\n";
        $js .= "});\n";
        $view->registerJs($js, $view::POS_READY);

        // add a click handler for the remove button
        $js = 'jQuery("#' . $this->formId . '").on("click", "' . $this->deleteButton . '", function(e) {'. "\n";
        $js .= "    e.preventDefault();\n";
        $js .= '    jQuery(".' .  $this->widgetContainer . '").yiiDynamicForm("deleteItem", '. $this->_hashVar . ", e, jQuery(this));\n";
        $js .= "});\n";
        $view->registerJs($js, $view::POS_READY);

        $js = 'jQuery("#' . $this->formId . '").yiiDynamicForm(' . $this->_hashVar .');' . "\n";
        $view->registerJs($js, $view::POS_LOAD);
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $content = ob_get_clean();
        /** @var AdvancedHtmlDom $dom */
        $dom = \futuretek\shared\dom\str_get_html($content);
        $this->_options['template'] = trim($dom->find($this->widgetItem)->html());

        /** @noinspection NotOptimalIfConditionsInspection */
        if (!$this->_options['preloadedModels'] && $this->model->isNewRecord && array_key_exists('min', $this->_options) && $this->_options['min'] === 0) {
            $content = $this->removeItems($content);
        }

        $this->hashOptions();
        $view = $this->getView();
        $widgetRegistered = $this->registerHashVarWidget();
        $this->_hashVar = $this->getHashVarName();

        if ($widgetRegistered) {
            $this->registerOptions($view);
            $this->registerAssets($view);
        }

        echo Html::tag('div', $content, ['class' => $this->widgetContainer, 'data-dynamicform' => $this->_hashVar]);
    }

    /**
     * Clear HTML widgetBody. Required to work with zero or more items.
     *
     * @param string $content
     * @return string
     */
    private function removeItems($content)
    {
        /** @var AdvancedHtmlDom $dom */
        $dom = \futuretek\shared\dom\str_get_html($content);
        $dom->find($this->widgetItem)->remove();
        return (string) $dom;
    }
}
