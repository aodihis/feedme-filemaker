<?php

namespace craftyfm\craftfeedmefilemaker;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\feedme\events\FeedDataEvent;
use craft\feedme\Plugin as FeedMe;
use craft\feedme\services\DataTypes;
use craftyfm\craftfeedmefilemaker\models\Settings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * feedme-filemaker plugin
 *
 * @method static FeedmeFilemaker getInstance()
 * @method Settings getSettings()
 * @author CraftyFm <stuart@x2network.net>
 * @copyright CraftyFm
 * @license https://craftcms.github.io/license/ Craft License
 */
class FeedmeFilemaker extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function () {
            $this->attachEventHandlers();
        });

    }

    /**
     * @throws InvalidConfigException
     */
    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws Exception
     * @throws LoaderError
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('feedme-filemaker/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        Event::on(DataTypes::class, DataTypes::EVENT_BEFORE_FETCH_FEED, function (FeedDataEvent $event) {
            // Get API token directly without caching
            $token = $this->getApiToken();

            if (!$token) {
                Craft::error('Failed to obtain API token', __METHOD__);
                return;
            }

            // Get the Feed Me plugin's settings
            $settings = [
                'feedOptions' => [
                    $event->feedId => [
                        'requestOptions' => [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => 'Bearer ' . $token,
                            ],
                        ],
                    ]
                ],
            ];

            // Feed back to the plugin
            FeedMe::getInstance()->setSettings($settings);
        });
    }

    private function getApiToken(): ?string
    {
        try {
            $client = new Client([
                'base_uri' => (string)$this->getSettings()->authURL,
                'verify' => false,
            ]);

            // Create Basic Auth string
            $basicAuthString = 'Basic ' . base64_encode($this->getSettings()->user . ':' . $this->getSettings()->pass);

            // Request token
            $response = $client->request('POST', '', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => $basicAuthString
                ],
                'body' => '',
                'debug' => false,
            ]);

            $json = $response->getBody()->getContents();
            $data = json_decode($json);

            if ($response->getStatusCode() === 200 && isset($data->response->token)) {
                return $data->response->token;
            }

            return null;

        } catch (\Exception|GuzzleException $e) {
            Craft::error('Failed to get API token: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }
}