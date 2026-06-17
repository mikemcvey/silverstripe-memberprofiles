<?php

namespace Symbiote\MemberProfiles\Tests;

use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\MemberProfiles\Pages\MemberProfilePage;
use Symbiote\MemberProfiles\Email\MemberConfirmationEmail;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;

/**
 * Tests for {@link MemberConfirmationEmail}.
 *
 * @package    silverstripe-memberprofiles
 * @subpackage tests
 */
class MemberConfirmationEmailTest extends SapphireTest
{
    protected $usesDatabase = true;

    /**
     * @usesDatabase
     */
    public function testGetParsedString()
    {
        $page   = MemberProfilePage::create();
        $member = Member::create();

        $member->Email     = 'email@domain.com';
        $member->FirstName = 'Test';
        $member->LastName  = 'User';
        $member->write();

        /**
         * @var DBDatetime $createdObj
         */
        $createdObj = $member->dbObject('Created');

        $raw = '<ul>
			<li>Cost: $10</li>
			<li>Site Name: $SiteName</li>
			<li>Login Link: $LoginLink</li>
			<li>Member:
				<ul>
					<li>Since: $Member.Created</li>
					<li>Email: $Member.Email</li>
					<li>Name: $Member.Name</li>
					<li>Surname: $Member.Surname</li>
				</ul>
			</li>
		</ul>';

        $email = MemberConfirmationEmail::create($page, $member);
        $loginLink = Controller::join_links(
            $email->BaseURL(),
            singleton(Security::class)->Link('login')
        );
        $expected = "<ul>
			<li>Cost: $10</li>
			<li>Site Name: " . SiteConfig::current_site_config()->Title . "</li>
			<li>Login Link: " . $loginLink . "</li>
			<li>Member:
				<ul>
					<li>Since: " . $createdObj->Nice() . "</li>
					<li>Email: {$member->Email}</li>
					<li>Name: {$member->Name}</li>
					<li>Surname: {$member->Surname}</li>
				</ul>
			</li>
		</ul>";
        $this->assertEquals(
            $expected,
            $email->getParsedString($raw),
            'All allowed variables are parsed into the string.'
        );
    }
}
