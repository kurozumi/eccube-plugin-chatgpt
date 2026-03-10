<?php

/*
 * This file is part of ChatGpt
 *
 * Copyright(c) Akira Kurozumi <info@a-zumi.net>
 *
 * https://a-zumi.net
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\ChatGpt\Tests\Web\Admin;

use Eccube\Common\Constant;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use Plugin\ChatGpt\Entity\ChatGpt;
use Plugin\ChatGpt\Repository\ChatGptRepository;
use Symfony\Component\Filesystem\Filesystem;

class ChatGptControllerTest extends AbstractAdminWebTestCase
{
    /** @var ChatGptRepository */
    private $chatGptRepository;

    public function setUp(): void
    {
        parent::setUp();

        $envFile = static::getContainer()->getParameter('kernel.project_dir').'/.env';
        $fs = new Filesystem();
        $fs->copy($envFile, $envFile.'.backup');

        $this->chatGptRepository = static::getContainer()->get(ChatGptRepository::class);
    }

    public function tearDown(): void
    {
        $envFile = static::getContainer()->getParameter('kernel.project_dir').'/.env';
        $fs = new Filesystem();
        $fs->rename($envFile.'.backup', $envFile, true);

        parent::tearDown();
    }

    public function testルーティングチェック(): void
    {
        $this->client->request('GET', $this->generateUrl('admin_chat_gpt_config'));
        self::assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testConfig()
    {
        $envFile = static::getContainer()->getParameter('kernel.project_dir').'/.env';

        $this->client->request('POST', $this->generateUrl('admin_chat_gpt_config'), [
            'chat_gpt' => [
                'apiKey' => 'test',
                'model' => 'test',
                Constant::TOKEN_NAME => 'dummy',
            ],
        ]);

        $env = file_get_contents($envFile);
        $key = 'OPENAI_API_KEY';

        if (preg_match('/^('.$key.')=(.*)/m', $env, $matches)) {
            self::assertEquals('test', $matches[2]);
        } else {
            self::fail(sprintf('%sが見つかりませんでした。', $key));
        }
    }

    public function test商品説明生成エンドポイントにGETリクエストは405(): void
    {
        $this->client->request('GET', $this->generateUrl('admin_chat_gpt_product'));
        self::assertEquals(405, $this->client->getResponse()->getStatusCode());
    }

    public function test商品説明生成エンドポイントにCSRFトークンなしは403(): void
    {
        $this->client->request(
            'POST',
            $this->generateUrl('admin_chat_gpt_product'),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['prompt' => 'テスト'])
        );
        self::assertEquals(403, $this->client->getResponse()->getStatusCode());
    }

    public function testニュース生成エンドポイントにGETリクエストは405(): void
    {
        $this->client->request('GET', $this->generateUrl('admin_chat_gpt_news'));
        self::assertEquals(405, $this->client->getResponse()->getStatusCode());
    }

    public function testニュース生成エンドポイントにCSRFトークンなしは403(): void
    {
        $this->client->request(
            'POST',
            $this->generateUrl('admin_chat_gpt_news'),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['prompt' => 'テスト'])
        );
        self::assertEquals(403, $this->client->getResponse()->getStatusCode());
    }

    public function test設定が保存される(): void
    {
        $this->client->request('POST', $this->generateUrl('admin_chat_gpt_config'), [
            'chat_gpt' => [
                'apiKey' => 'sk-test-api-key',
                'model' => 'gpt-4o',
                'product' => '商品説明用のシステムプロンプト',
                'news' => 'ニュース用のシステムプロンプト',
                Constant::TOKEN_NAME => 'dummy',
            ],
        ]);

        /** @var ChatGpt $chatGpt */
        $chatGpt = $this->chatGptRepository->get(ChatGpt::ID);

        self::assertEquals('sk-test-api-key', $chatGpt->getApiKey());
        self::assertEquals('gpt-4o', $chatGpt->getModel());
        self::assertEquals('商品説明用のシステムプロンプト', $chatGpt->getProduct());
        self::assertEquals('ニュース用のシステムプロンプト', $chatGpt->getNews());
    }
}
