<?php

namespace Drupal\Tests\message_subscribe_email\Unit;

use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message_subscribe_email\Manager;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the message subscribe email manager utility class.
 *
 * @coversDefaultClass \Drupal\message_subscribe_email\Manager
 *
 * @group message_subscribe_email
 */
class ManagerTest extends UnitTestCase {

  /**
   * Tests the flag retrieval.
   *
   * @param array $expected
   *   The expected flags.
   * @param array $flags
   *   The available flags for the flag service.
   *
   * @covers ::getFlags
   *
   * @dataProvider providerTestGetFlags
   */
  public function testGetFlags(array $expected, array $flags) {
    $flag_service = $this->prophesize(FlagServiceInterface::class);
    $flag_service->getFlags()->willReturn($flags);

    $manager = new Manager($flag_service->reveal());
    $this->assertEquals($expected, $manager->getFlags());
  }

  /**
   * Data provider for testGetFlags().
   *
   * @return array
   *   An array of arguments for self::testGetFlags().
   */
  public function providerTestGetFlags() {
    // No flags.
    $return[] = [[], []];

    // No matching flags.
    $flag = $this->prophesize(FlagInterface::class)->reveal();
    $return[] = [[], ['foo_flag' => $flag]];

    // A few matching flags.
    $return[] = [
      ['email_one' => $flag, 'email_two' => $flag],
      ['foo_flag' => $flag, 'email_one' => $flag, 'email_two' => $flag],
    ];

    return $return;
  }

}
