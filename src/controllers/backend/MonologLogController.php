<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Monolog\controllers\backend;

use Besnovatyj\Monolog\services\MonologLogManageService;
use Exception;
use Yii;

use yii\helpers\VarDumper;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Контроллер для управления Monolog логами
 */
class MonologLogController extends \yii\web\Controller
{
    use \common\components\controller\ControllerTrait;
    private MonologLogManageService $service;

    public function __construct($id, $module, MonologLogManageService $service, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->service = $service;
    }

    /**
     * Список основных (текущих) лог-файлов
     */
    public function actionIndex(): string
    {
        return $this->render('index', [
            'dataProvider' => $this->service->getDataProvider('all')
        ]);
    }

    /**
     * Список ротированных лог-файлов
     */
    public function actionIndexHistory(): string
    {
        return $this->render('index-history', [
            'dataProvider' => $this->service->getDataProvider('history')
        ]);
    }

    /**
     * Список архивированных (ZIP) лог-файлов
     */
    public function actionIndexZip(): string
    {
        return $this->render('index-zip', [
            'dataProvider' => $this->service->getDataProvider('zip')
        ]);
    }

    /**
     * Просмотр лог-файла с форматированием JSON
     */
    public function actionView($slug, $page = 1): Response|string
    {
        try {
            $log = $this->service->find($slug);
            $perPage = 100; // Количество записей на страницу

            $data = $this->service->getLogEntries($slug, $page, $perPage);

            return $this->render('view', [
                'log' => $log,
                'entries' => $data['entries'],
                'total' => $data['total'],
                'pages' => $data['pages'],
                'currentPage' => $data['currentPage'],
                'perPage' => $data['perPage'],
            ]);
        } catch (Exception $e) {
            Yii::$app->errorHandler->logException($e);
            if (YII_DEBUG) {
                Yii::$app->session->setFlash('error', VarDumper::dumpAsString($e->getMessage()));
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка');
            }
        }
        return $this->goReferer();
    }

    /**
     * Принудительная ротация текущего файла (начать текущий лог заново,
     * содержимое уходит в историю).
     */
    public function actionRotate($slug): Response
    {
        try {
            $this->service->rotate($slug);
            Yii::$app->session->setFlash('success', 'Rotate success');
            return $this->goReferer();
        } catch (Exception $e) {
            Yii::$app->errorHandler->logException($e);
            if (YII_DEBUG) {
                Yii::$app->session->setFlash('error', VarDumper::dumpAsString($e->getMessage()));
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка');
            }
        }
        return $this->goReferer();
    }

    /**
     * История ротации для лог-файла
     */
    public function actionHistory($slug): Response|string
    {
        try {
            $data = $this->service->getHistory($slug);
            return $this->render('history', [
                'log' => $data['log'],
                'dataProvider' => $data['data_provider'],
                'fullSize' => $data['fullSize'],
            ]);
        } catch (NotFoundHttpException $e) {
            Yii::$app->errorHandler->logException($e);
            if (YII_DEBUG) {
                Yii::$app->session->setFlash('error', VarDumper::dumpAsString($e->getMessage()));
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка');
            }
        }
        return $this->goReferer();
    }

    /**
     * Удаление лог-файла
     */
    public function actionDelete($slug, $since = null): Response
    {
        try {
            $this->service->deleteLog($slug, $since);
            Yii::$app->session->setFlash('success', 'Delete success.');
        } catch (Exception $e) {
            Yii::$app->errorHandler->logException($e);
            if (YII_DEBUG) {
                Yii::$app->session->setFlash('error', VarDumper::dumpAsString($e->getMessage()));
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка');
            }
        }
        return $this->goReferer();
    }

    /**
     * Скачивание лог-файла
     */
    public function actionDownload($slug): void
    {
        try {
            $log = $this->service->find($slug);
            Yii::$app->response->sendFile($log->getFilePath())->send();
        } catch (Exception $e) {
            Yii::$app->errorHandler->logException($e);
            if (YII_DEBUG) {
                Yii::$app->session->setFlash('error', VarDumper::dumpAsString($e->getMessage()));
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка');
            }
        }
    }

    /**
     * Создание ZIP архива лог-файла
     */
    public function actionZip($slug): Response
    {
        try {
            $this->service->zip($slug);
            Yii::$app->session->setFlash('success', 'Zip success');
            return $this->redirect(['/Monolog/backend/monolog-log/index-zip']);
        } catch (Exception $e) {
            Yii::$app->errorHandler->logException($e);
            if (YII_DEBUG) {
                Yii::$app->session->setFlash('error', VarDumper::dumpAsString($e->getMessage()));
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка');
            }
        }
        return $this->goReferer();
    }

    /**
     * AJAX endpoint для получения записей лога в JSON формате
     */
    public function actionGetEntries($slug, $page = 1): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            $perPage = 100;
            $data = $this->service->getLogEntries($slug, $page, $perPage);

            return [
                'success' => true,
                'data' => $data
            ];
        } catch (Exception $e) {
            Yii::$app->errorHandler->logException($e);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
