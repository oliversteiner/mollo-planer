<?php

namespace Tests\Feature\GraphQL;

use App\Support\AppSettings;

/**
 * appSettings and updateAppSettings. What the switch does at login is in
 * TwoFactorSwitchTest.
 */
class AppSettingsTest extends GraphQLTestCase
{
    private const SETTINGS = '{ appSettings { twoFactorEnabled twoFactorSwitchable } }';

    private const SWITCH = 'mutation ($on: Boolean!) { updateAppSettings(twoFactorEnabled: $on) { twoFactorEnabled twoFactorSwitchable } }';

    public function test_two_factor_is_on_by_default_and_switchable_outside_production(): void
    {
        $this->as('member')->gql(self::SETTINGS)
            ->assertJsonPath('data.appSettings', ['twoFactorEnabled' => true, 'twoFactorSwitchable' => true]);
    }

    public function test_admin_switches_two_factor_off_and_on(): void
    {
        $this->as('admin')->gql(self::SWITCH, ['on' => false])
            ->assertJsonPath('data.updateAppSettings', ['twoFactorEnabled' => false, 'twoFactorSwitchable' => true]);
        $this->assertFalse(AppSettings::twoFactorEnabled());

        $this->as('admin')->gql(self::SWITCH, ['on' => true])
            ->assertJsonPath('data.updateAppSettings.twoFactorEnabled', true);
        $this->assertTrue(AppSettings::twoFactorEnabled());
    }

    public function test_owner_without_admin_role_cannot_switch(): void
    {
        $this->assertUnauthorized($this->as('owner')->gql(self::SWITCH, ['on' => false]));

        $this->assertTrue(AppSettings::twoFactorEnabled());
    }

    public function test_in_production_the_switch_is_refused_and_two_factor_stays_on(): void
    {
        AppSettings::setTwoFactorEnabled(false, $this->users['admin']);
        $this->app['env'] = 'production';
        // Outside "testing" Laravel checks the CSRF token again.
        $this->withSession(['_token' => 'csrf-token'])->withHeader('X-CSRF-TOKEN', 'csrf-token');

        $this->assertTrue(AppSettings::twoFactorEnabled());
        $this->as('admin')->gql(self::SWITCH, ['on' => false])
            ->assertJsonPath('errors.0.extensions.validation.twoFactorEnabled.0', __('graphql.two_factor_not_switchable'));
        $this->as('admin')->gql(self::SETTINGS)
            ->assertJsonPath('data.appSettings', ['twoFactorEnabled' => true, 'twoFactorSwitchable' => false]);
    }
}
