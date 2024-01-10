<?php

namespace craftyfm\craftfeedmefilemaker;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterTemplateRootsEvent;
use craft\feedme\events\FeedDataEvent;
use craft\feedme\Plugin as FeedMe;
use craft\feedme\services\DataTypes;
use craft\web\View;
use craftyfm\craftfeedmefilemaker\models\Settings;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use yii\base\Event;

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

        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['something'] = __DIR__ . '/template-two';
            }
        );
        // $this->createOverrideFolder();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
            // ...

        });

        Event::on(DataTypes::class, DataTypes::EVENT_BEFORE_FETCH_FEED, function(FeedDataEvent $event) {

            $token = Craft::$app->getCache()->getOrSet('api-token', function () {
                // Create Guzzle client
                // file to store cookie data
                $cookieFile = '../cookie_jar.txt';
                $cookieJar = new FileCookieJar($cookieFile, TRUE);
                $client = new Client([
                    'base_uri' => (string)$this->getSettings()->authURL ,
                    'verify' => false,
                    'cookies' => $cookieJar,

                ]);

                //create Basic Auth string
                $basicAuthString = 'Basic ' . base64_encode($this->getSettings()->user .':'.$this->getSettings()->pass);


                // Request token
                $response = $client->request('POST', '', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => $basicAuthString

                    ],
                    ['body' => ''],
                    'debug' => true,
                ]);




                //$data = json_decode($response->getBody()->getContents());
                $json = $response->getBody()->getContents();
                $data = json_decode($json);

                $status = $response->getStatusCode();

                $authtoken = $data->response->token;
                //   Craft::info(dd($data));
                //   Craft::info(dd($data->response->token));
                //  Craft::info((string)$data);


                if ($status === 200) {
                    // $body = $data;

                    return $authtoken;
                    // return '147a30e2bcdd3b235672e7d5eecadb6d886e7f956421e15c6722';
                } else {
                    return false;
                }
            }, 900);



            // Get the Feed Me plugin's settings
            $settings = FeedMe::getInstance()->getSettings();

            // Add the access token to the settings

            $settings = [ 'feedOptions' => [
                $event->feedId => [
                    'requestOptions' => [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => 'Bearer ' . $token, //'c312f791d7eaf0425571d577f27ce0f2e0d339c355516a7bc213',
                        ],
                    ],
                ]
            ],
            ];




            // Feed back to the plugin
            FeedMe::getInstance()->setSettings((array) $settings);
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('feedme-filemaker/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/4.x/extend/events.html to get started)
    }
}
