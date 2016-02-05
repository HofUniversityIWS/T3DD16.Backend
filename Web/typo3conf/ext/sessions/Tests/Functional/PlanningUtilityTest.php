<?php
namespace T3DD\Sessions\Tests\Functional;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Tests\FunctionalTestCase;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;
use TYPO3\Sessions\Domain\Model\AnySession;
use TYPO3\Sessions\PlanningUtility;

/**
 * Class PlanningUtilityTest
 * @package T3DD\Sessions\Tests\Unit
 */
class PlanningUtilityTest extends FunctionalTestCase
{
    /**
     * @var array
     */
    protected $testExtensionsToLoad = array(
        'typo3conf/ext/sessions',
    );

    /**
     * @var PlanningUtility
     */
    protected $subject;

    /**
     * Sets up this test case.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->importDataSet(__DIR__ . '/Fixtures/Xml/fe_users.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/Xml/tx_sessions_domain_model_session.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/Xml/tx_sessions_session_record_mm.xml');

        $this->subject = $this->getAccessibleMock(PlanningUtility::class, array('_dummy'), array());
    }

    /**
     * Tears down this test case.
     */
    protected function tearDown()
    {
        parent::tearDown();

        unset($this->subject);
    }

    /**
     * @param string $begin
     * @param string $end
     * @param array $speakerIds
     * @param bool $expected
     *
     * @test
     * @dataProvider collidingSessionsAreDeterminedDataProvider
     */
    public function collidingSessionsAreDetermined($begin, $end, array $speakerIds, $expected)
    {
        $beginDateTime = \DateTime::createFromFormat(DATE_ISO8601, $begin);
        $endDateTime = \DateTime::createFromFormat(DATE_ISO8601, $end);

        $session = new AnySession();
        $session->setBegin($beginDateTime);
        $session->setEnd($endDateTime);

        foreach ($speakerIds as $speakerId) {
            $speaker = new FrontendUser();
            $speaker->_setProperty('uid', $speakerId);
            $session->addSpeaker($speaker);
        }

        $result = $this->subject->getCollidingSessions($session);
        $this->assertEquals($expected, (is_array($result) ? count($result) : $result));
    }

    /**
     * @return array
     */
    public function collidingSessionsAreDeterminedDataProvider()
    {
        return [
            'different speaker, before' => [
                '2016-09-01T12:00:00Z', '2016-09-01T13:00:00Z', [1,3], false,
            ],
            'different speaker, after' => [
                '2016-09-01T16:00:00Z', '2016-09-01T17:00:00Z', [1,3], false,
            ],
            'different speaker, start time intersects' => [
                '2016-09-01T14:30:00Z', '2016-09-01T15:30:00Z', [1,3], false,
            ],
            'different speaker, end time intersects' => [
                '2016-09-01T13:30:00Z', '2016-09-01T14:30:00Z', [1,3], false,
            ],
            'different speaker, session time intersects' => [
                '2016-09-01T14:15:00Z', '2016-09-01T14:45:00Z', [1,3], false,
            ],
            'different speaker, session time surrounds' => [
                '2016-09-01T13:00:00Z', '2016-09-01T17:00:00Z', [1,3], false,
            ],
            'different speaker, session time equals' => [
                '2016-09-01T14:00:00Z', '2016-09-01T15:00:00Z', [1,3], false,
            ],

            'same speaker, before' => [
                '2016-09-01T12:00:00Z', '2016-09-01T13:00:00Z', [2,3], false,
            ],
            'same speaker, after' => [
                '2016-09-01T16:00:00Z', '2016-09-01T17:00:00Z', [2,3], false,
            ],
            'same speaker, start time intersects' => [
                '2016-09-01T14:30:00Z', '2016-09-01T15:30:00Z', [2,3], 1,
            ],
            'same speaker, end time intersects' => [
                '2016-09-01T13:30:00Z', '2016-09-01T14:30:00Z', [2,3], 1,
            ],
            'same speaker, session time intersects' => [
                '2016-09-01T14:15:00Z', '2016-09-01T14:45:00Z', [2,3], 1,
            ],
            'same speaker, session time surrounds' => [
                '2016-09-01T13:00:00Z', '2016-09-01T17:00:00Z', [2,3], 1,
            ],
            'same speaker, session time equals' => [
                '2016-09-01T14:00:00Z', '2016-09-01T15:00:00Z', [2,3], 1,
            ],
        ];
    }
}