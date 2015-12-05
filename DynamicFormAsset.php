<?php
namespace futuretek\form\dynamicform;

/**
 * Asset bundle for dynamicform Widget
 *
 * @author Petr Leo Compel <petr.compel@futuretek.cz>
 */
class DynamicFormAsset extends \yii\web\AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = '@vendor/futuretek/yii2-dynamicform/assets';
        $this->depends = [
            'yii\web\JqueryAsset',
            'yii\widgets\ActiveFormAsset'
        ];
        $this->js = YII_ENV === 'dev' ? ['yii2-dynamic-form.js'] : ['yii2-dynamic-form.min.js'];
        parent::init();
    }
}
