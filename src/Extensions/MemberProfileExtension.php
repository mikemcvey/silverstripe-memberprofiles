<?php

namespace Symbiote\MemberProfiles\Extensions;

use Symbiote\MemberProfiles\Pages\MemberProfilePage;
use Symbiote\MemberProfiles\Email\MemberConfirmationEmail;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Validation\ValidationResult;


/**
 * Adds validation fields to the Member object, as well as exposing the user's
 * status in the CMS.
 *
 * @package silverstripe-memberprofiles
 */
class MemberProfileExtension extends Extension
{
    private static $db = [
        'ValidationKey' => 'Varchar(40)',
        'NeedsValidation' => 'Boolean',
        'NeedsApproval' => 'Boolean',
        'PublicFieldsRaw' => 'Text'
    ];

    private static $has_one = [
        'ProfilePage' => MemberProfilePage::class
    ];

    public function getPublicFields()
    {
        return (array) unserialize($this->getOwner()->getField('PublicFieldsRaw') ?? '');
    }

    public function setPublicFields($fields)
    {
        $this->getOwner()->setField('PublicFieldsRaw', serialize($fields));
    }

    public function canLogIn(ValidationResult $result)
    {
        if ($this->getOwner()->NeedsApproval) {
            $result->addError(_t(
                'MemberProfiles.NEEDSAPPROVALTOLOGIN',
                'An administrator must confirm your account before you can log in.'
            ));
        }
        if ($this->getOwner()->NeedsValidation) {
            $result->addError(_t(
                'MemberProfiles.NEEDSVALIDATIONTOLOGIN',
                'You must validate your account before you can log in.'
            ));
        }
    }

    /**
     * Allows admin users to manually confirm a user.
     */
    public function saveManualEmailValidation($value)
    {
        if ($value === 'confirm') {
            $this->getOwner()->NeedsValidation = false;
        } elseif ($value === 'resend') {
            $email = MemberConfirmationEmail::create($this->getOwner()->ProfilePage(), $this->getOwner());
            $email->send();
        }
    }

    public function onAfterPopulateDefaults()
    {
        $this->getOwner()->ValidationKey = sha1(mt_rand() . mt_rand());
    }

    public function onAfterWrite()
    {
        $changed = $this->getOwner()->getChangedFields();

        if (array_key_exists('NeedsApproval', $changed)) {
            $before = $changed['NeedsApproval']['before'];
            $after  = $changed['NeedsApproval']['after'];
            $page   = $this->getOwner()->ProfilePage();
            $email  = $page->EmailType;

            if ($before == true && $after == false && $email != 'None') {
                $email = MemberConfirmationEmail::create($page, $this->getOwner());
                $email->send();
            }
        }
    }

    public function updateMemberFormFields($fields)
    {
        $fields->removeByName('ValidationKey');
        $fields->removeByName('NeedsValidation');
        $fields->removeByName('NeedsApproval');
        $fields->removeByName('ProfilePageID');
        $fields->removeByName('PublicFieldsRaw');

        // For now we just pass an empty array as the list of selectable groups -
        // it's up to anything that uses this to populate it appropriately
        $existing = $this->getOwner()->Groups();
        $fields->push(CheckboxSetField::create('Groups', 'Groups', [], $existing));
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('ValidationKey');
        $fields->removeByName('NeedsValidation');
        $fields->removeByName('NeedsApproval');
        $fields->removeByName('ProfilePageID');
        $fields->removeByName('PublicFieldsRaw');

        // Remove member profile fields, as they may have been added by this method being called
        // multiple times.
        $fields->removeByName('ApprovalHeader');
        $fields->removeByName('ApprovalNote');
        $fields->removeByName('ConfirmationHeader');
        $fields->removeByName('ConfirmationNote');

        if ($this->getOwner()->NeedsApproval) {
            $note = _t(
                'MemberProfiles.NOLOGINUNTILAPPROVED',
                'This user has not yet been approved. They cannot log in until their account is approved.'
            );

            $fields->addFieldsToTab('Root.Main', [
                // ApprovalAnchor is used by MemberApprovalController (2017-02-01)
                LiteralField::create('ApprovalAnchor', "<div id=\"MemberProfileRegistrationApproval\"></div>"),
                HeaderField::create('ApprovalHeader', _t('MemberProfiles.REGAPPROVAL', 'Registration Approval')),
                LiteralField::create('ApprovalNote', "<p>$note</p>"),
                DropdownField::create('NeedsApproval', '', [
                    true  => _t('MemberProfiles.DONOTCHANGE', 'Do not change'),
                    false => _t('MemberProfiles.APPROVETHISMEMBER', 'Approve this member')
                ]),
            ]);
        }

        if ($this->getOwner()->NeedsValidation) {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    HeaderField::create('ConfirmationHeader', _t('MemberProfiles.EMAILCONFIRMATION', 'Email Confirmation')),
                    LiteralField::create('ConfirmationNote', '<p>' . _t(
                        'MemberProfiles.NOLOGINTILLCONFIRMED',
                        'The member cannot log in until their account is confirmed.'
                    ) . '</p>'),
                    DropdownField::create('ManualEmailValidation', '', [
                        'unconfirmed' => _t('MemberProfiles.UNCONFIRMED', 'Unconfirmed'),
                        'resend' => _t('MemberProfiles.RESEND', 'Resend confirmation email'),
                        'confirm' => _t('MemberProfiles.MANUALLYCONFIRM', 'Manually confirm')
                    ])
                ]
            );
        }
    }
}
