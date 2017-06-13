<?php

namespace Tests\Wallabag\CoreBundle\Controller;

use Tests\Wallabag\CoreBundle\WallabagCoreTestCase;

class RssControllerTest extends WallabagCoreTestCase
{
    public function validateDom($xml, $type, $nb = null)
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $xpath = new \DOMXpath($doc);
        $xpath->registerNamespace('a', 'http://www.w3.org/2005/Atom');

        if (null === $nb) {
            $this->assertGreaterThan(0, $xpath->query('//a:entry')->length);
        } else {
            $this->assertEquals($nb, $xpath->query('//a:entry')->length);
        }

        $this->assertEquals(1, $xpath->query('/a:feed')->length);

        $this->assertEquals(1, $xpath->query('/a:feed/a:title')->length);
        $this->assertEquals('wallabag — '.$type.' feed', $xpath->query('/a:feed/a:title')->item(0)->nodeValue);

        $this->assertEquals(1, $xpath->query('/a:feed/a:updated')->length);

        $this->assertEquals(1, $xpath->query('/a:feed/a:generator')->length);
        $this->assertEquals('wallabag', $xpath->query('/a:feed/a:generator')->item(0)->nodeValue);

        $this->assertEquals(1, $xpath->query('/a:feed/a:subtitle')->length);
        $this->assertEquals('RSS feed for '.$type.' entries', $xpath->query('/a:feed/a:subtitle')->item(0)->nodeValue);

        $this->assertEquals(1, $xpath->query('/a:feed/a:link[@rel="self"]')->length);
        $this->assertContains($type, $xpath->query('/a:feed/a:link[@rel="self"]')->item(0)->getAttribute('href'));

        $this->assertEquals(1, $xpath->query('/a:feed/a:link[@rel="last"]')->length);

        foreach ($xpath->query('//a:entry') as $item) {
            $this->assertEquals(1, $xpath->query('a:title', $item)->length);
            $this->assertEquals(1, $xpath->query('a:link[@rel="via"]', $item)->length);
            $this->assertEquals(1, $xpath->query('a:link[@rel="alternate"]', $item)->length);
            $this->assertEquals(1, $xpath->query('a:id', $item)->length);
            $this->assertEquals(1, $xpath->query('a:published', $item)->length);
            $this->assertEquals(1, $xpath->query('a:content', $item)->length);
        }
    }

    public function dataForBadUrl()
    {
        return [
            [
                '/feed/admin/YZIOAUZIAO/unread',
            ],
            [
                '/feed/wallace/YZIOAUZIAO/starred',
            ],
            [
                '/feed/wallace/YZIOAUZIAO/archives',
            ],
        ];
    }

    /**
     * @dataProvider dataForBadUrl
     */
    public function testBadUrl($url)
    {
        $client = $this->getClient();

        $client->request('GET', $url);

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testUnread()
    {
        $client = $this->getClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em
            ->getRepository('WallabagUserBundle:User')
            ->findOneByUsername('admin');

        $config = $user->getConfig();
        $config->setRssToken('SUPERTOKEN');
        $config->setRssLimit(2);
        $em->persist($config);
        $em->flush();

        $client->request('GET', '/feed/admin/SUPERTOKEN/unread');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $this->validateDom($client->getResponse()->getContent(), 'unread', 2);
    }

    public function testStarred()
    {
        $client = $this->getClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em
            ->getRepository('WallabagUserBundle:User')
            ->findOneByUsername('admin');

        $config = $user->getConfig();
        $config->setRssToken('SUPERTOKEN');
        $config->setRssLimit(1);
        $em->persist($config);
        $em->flush();

        $client = $this->getClient();
        $client->request('GET', '/feed/admin/SUPERTOKEN/starred');

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 1);

        $this->validateDom($client->getResponse()->getContent(), 'starred');
    }

    public function testArchives()
    {
        $client = $this->getClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em
            ->getRepository('WallabagUserBundle:User')
            ->findOneByUsername('admin');

        $config = $user->getConfig();
        $config->setRssToken('SUPERTOKEN');
        $config->setRssLimit(null);
        $em->persist($config);
        $em->flush();

        $client = $this->getClient();
        $client->request('GET', '/feed/admin/SUPERTOKEN/archive');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $this->validateDom($client->getResponse()->getContent(), 'archive');
    }

    public function testPagination()
    {
        $client = $this->getClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em
            ->getRepository('WallabagUserBundle:User')
            ->findOneByUsername('admin');

        $config = $user->getConfig();
        $config->setRssToken('SUPERTOKEN');
        $config->setRssLimit(1);
        $em->persist($config);
        $em->flush();

        $client = $this->getClient();

        $client->request('GET', '/feed/admin/SUPERTOKEN/unread');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->validateDom($client->getResponse()->getContent(), 'unread');

        $client->request('GET', '/feed/admin/SUPERTOKEN/unread/2');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->validateDom($client->getResponse()->getContent(), 'unread');

        $client->request('GET', '/feed/admin/SUPERTOKEN/unread/3000');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }
}
