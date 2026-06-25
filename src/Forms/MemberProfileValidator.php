<?php

namespace Symbiote\MemberProfiles\Forms;

use Override;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Security\Security;
use Symbiote\MemberProfiles\Model\MemberProfileField;
use SilverStripe\Security\Member;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;

/**
 * This validator provides the unique and required functionality for {@link MemberProfileField}s.
 *
 * @package silverstripe-memberprofiles
 */
class MemberProfileValidator extends RequiredFieldsValidator
{
    // remove for SS6? public Form $form;

    /**
     * @var array
     */
    protected $unique = [];

    /**
     * @param FieldList|MemberProfileField[] $fields
     * @param Member|null $member
     */
    public function __construct(protected $fields, protected $member = null)
    {
        foreach ($this->fields as $field) {
            if ($field->Required && $field->ProfileVisibility !== 'Readonly') {
                $this->addRequiredField($field->MemberField);
            }

            if ($field->Unique) {
                $this->unique[] = $field->MemberField;
            }
        }

        if ($this->member && $this->member->ID && $this->member->Password) {
            $this->removeRequiredField('Password');
        }
    }

    /**
     * JavaScript validation is disabled on profile forms.
     */
    public function javascript(): null
    {
        return null;
    }

    #[Override]
    public function php($data)
    {
        $member = $this->member;
        $valid  = true;

        foreach ($this->unique as $field) {
            /**
             * @var Member|null $other
             */
            $other = DataObject::get_one(
                Member::class,
                sprintf('"%s" = \'%s\'', Convert::raw2sql($field), Convert::raw2sql($data[$field]))
            );

            $isEmail = $field === 'Email';
            $emailOK = !$isEmail;
            if ($isEmail) {
                $existing = Member::get()->filter(['Email:nocase' => $data['Email']]);

                // This ensures the existing member isn't the same as the current member, in case they're updating information.

                if ($current = Security::getCurrentUser()) {
                    $existing = $existing->filter(['ID:not' => $current->ID]);
                }

                $emailOK = !$existing->first();
            }

            if ($other && (!$member || !$member->exists() || $other->ID != $member->ID) || !$emailOK) {
                $fieldInstance = $this->form->Fields()->dataFieldByName($field);

                if ($fieldInstance->getCustomValidationMessage()) {
                    $message = $fieldInstance->getCustomValidationMessage();
                } else {
                    $message = sprintf(
                        _t('MemberProfiles.MEMBERWITHSAME', 'There is already a member with the same %s.'),
                        $field
                    );
                }

                $valid = false;
                $this->validationError($field, $message, 'required');
            }
        }

        // Create a dummy member as this is required for custom password validators
        if (isset($data['Password']) && $data['Password'] !== "") {
            if (is_null($member)) {
                $member = Member::create();

                //pass in the Unique Identifier Field (usually Email)
                $idField = Member::config()->get('unique_identifier_field');
                if (isset($data[$idField])) {
                    $member->$idField = $data[$idField];
                }
            }

            if ($validator = $member::password_validator()) {
                $results = $validator->validate($data['Password'], $member);

                if (!$results->isValid()) {
                    $valid = false;
                    foreach ($results->getMessages() as $value) {
                        if (isset($value['message'])) {
                            $this->validationError('Password', $value['message'], 'required');
                        }
                    }
                }
            }
        }

        return $valid && parent::php($data);
    }
}
