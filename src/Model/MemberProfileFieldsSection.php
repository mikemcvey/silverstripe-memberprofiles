<?php

namespace Symbiote\MemberProfiles\Model;

use Override;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;

/**
 * A profile section that displays a list of fields that have been marked as
 * public.
 *
 * @package    silverstripe-memberprofiles
 * @subpackage dataobjects
 */
class MemberProfileFieldsSection extends MemberProfileSection
{
    private static $table_name = 'MemberProfileFieldsSection';

    public function getDefaultTitle()
    {
        return _t('MemberProfiles.PROFILEFIELDSLIST', 'Profile Fields List');
    }

    #[Override]
    public function forTemplate()
    {
        return $this->renderWith(MemberProfileFieldsSection::class);
    }

    public function Fields()
    {
        $fields = $this->Parent()->Fields()->where('"PublicVisibility" <> \'Hidden\'');
        $public = $this->getMember()->getPublicFields();
        $result = ArrayList::create();

        foreach ($fields as $field) {
            if ($field->PublicVisibility == 'MemberChoice') {
                if (!in_array($field->MemberField, $public)) {
                    continue;
                }
            }

            $result->push(ArrayData::create([
                'Title' => $field->Title,
                'Value' => $this->getMember()->{$field->MemberField}
            ]));
        }

        return $result;
    }

    #[Override]
    public function ShowTitle()
    {
        return false;
    }
}
