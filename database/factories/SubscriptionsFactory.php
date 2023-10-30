<?php

namespace Database\Factories;

use App\Subscriptions;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Class SubscriptionsFactory for create subscriptions data for testing purpose
 * @package Database\Factories
 */
class SubscriptionsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscriptions::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => User::factory()->create()->id,
            'name' => $this->faker->randomElement(['GroupKit Pro', 'GroupKit Pro Annual', 'GroupKit Basic']),
            'stripe_id' => 'plan_' . Str::random(10),
            'stripe_plan' => 'plan_' . Str::random(10),
            'stripe_status' => 'active',
            'current_period_start' => Carbon::now(),
            'current_period_end' => $this->faker->dateTimeBetween(
                $startDate = Carbon::now(),
                $startDate->format('Y-m-d H:i:s') . ' +2 days'
            ),
            'quantity' => 1,
        ];
    }
}
