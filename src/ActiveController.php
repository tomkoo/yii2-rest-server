<?php

namespace p4it\rest\server;

use modules\core\common\components\ActiveDataFilter;
use Yii;
use yii\data\ActiveDataProvider;
use yii\rest\Serializer;
use yii\web\ForbiddenHttpException;
use yii\web\UnauthorizedHttpException;

class ActiveController extends \yii\rest\ActiveController
{
    public $searchModelClass;

    /**
     * @inheritdoc
     */
    public $serializer = [
        'class' => Serializer::class,
        'collectionEnvelope' => 'items',
    ];

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        $actions = [
            'index' => [
                'class' => IndexAction::class,
                'modelClass' => $this->modelClass,
                'checkAccess' => [$this, 'checkAccess'],
            ],
            'view' => [
                'class' => ViewAction::class,
                'modelClass' => $this->modelClass,
                'checkAccess' => [$this, 'checkAccess'],
            ],
            'create' => [
                'class' => CreateAction::class,
                'modelClass' => $this->modelClass,
                'checkAccess' => [$this, 'checkAccess'],
                'scenario' => $this->createScenario,
            ],
            'update' => [
                'class' => UpdateAction::class,
                'modelClass' => $this->modelClass,
                'checkAccess' => [$this, 'checkAccess'],
                'scenario' => $this->updateScenario,
            ],
            'delete' => [
                'class' => DeleteAction::class,
                'modelClass' => $this->modelClass,
                'checkAccess' => [$this, 'checkAccess'],
            ],
            'options' => [
                'class' => OptionsAction::class,
            ],
        ];

        if ($this->searchModelClass) {
            $actions['index']['dataFilter'] = [
                'class' => ActiveDataFilter::class,
                'searchModel' => $this->searchModelClass,
            ];
        }

        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];

        return $actions;
    }

    /**
     * ha action-onként külön jogosultság kezelés van, akkor ezt kell kifejteni a controller szinten is
     * tovább szűkítve a már megadott searchQuery-t
     *
     * @param string $action
     * @param null $model
     * @param array $params
     * @throws UnauthorizedHttpException
     * @throws ForbiddenHttpException
     */
    public function checkAccess($action, $model = null, $params = [])
    {
        if (!$model || $this->searchModelClass === null) {
            return parent::checkAccess($action, $model, $params);
        }

        /* item protection */
        $searchModelClass = $this->searchModelClass;
        $searchModel = new $searchModelClass();

        if (method_exists($searchModel, 'searchQuery')) {
            $model = $searchModel->searchQuery()->andWhere($model->getPrimaryKey(true))->one();
            if ($model === null) {
                throw new UnauthorizedHttpException();
            }
        }

        return parent::checkAccess($action, $model, $params); // TODO: Change the autogenerated stub
    }

    public function prepareDataProvider(IndexAction $action, $filter)
    {
        $requestParams = Yii::$app->getRequest()->getBodyParams();
        if (empty($requestParams)) {
            $requestParams = Yii::$app->getRequest()->getQueryParams();
        }

        if (method_exists($action->dataFilter->searchModel, 'searchQuery')) {
            $query = $action->dataFilter->searchModel->searchQuery();
        } else {
            /* @var $modelClass \yii\db\BaseActiveRecord */
            $modelClass = $this->modelClass;

            $query = $modelClass::find();
        }


        if (!empty($filter)) {
            $query->andWhere($filter);
        }

        return Yii::createObject([
            'class' => ActiveDataProvider::class,
            'query' => $query,
            'pagination' => [
                'params' => $requestParams,
            ],
            'sort' => [
                'params' => $requestParams,
            ],
        ]);
    }

    /**
     * we could maybe maintain options as well based on this.
     *
     * @param array $actions
     * @param mixed ...$actionsToUnset
     */
    protected function unsetActions(array &$actions, ...$actionsToUnset): void
    {
        foreach ($actionsToUnset as $item) {
            unset($actions[$item]);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function verbs()
    {
        return [
            'index' => ['GET', 'HEAD', 'POST'],
            'view' => ['GET', 'HEAD'],
            'create' => ['POST'],
            'update' => ['PUT', 'PATCH'],
            'delete' => ['DELETE'],
        ];
    }
}
