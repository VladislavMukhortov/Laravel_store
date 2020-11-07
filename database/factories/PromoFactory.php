<?php

namespace Database\Factories;

use App\Models\Promo;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Promo::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $type = (rand(1, 2) == 1) ? "amount_off" : "percent_off";

        $this->faker->addProvider(new \Bezhanov\Faker\Provider\Commerce($this->faker));
        return [
            'name' => $this->faker->productName,
            'type' => $type,
            'value' => rand(1, 100),
            'code' => $this->faker->numberBetween(10000, 99999),
        ];
    }
}
