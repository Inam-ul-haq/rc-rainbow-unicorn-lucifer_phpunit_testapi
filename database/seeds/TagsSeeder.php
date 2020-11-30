<?php

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

// @codingStandardsIgnoreLine
class TagsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Tag::class, 5)->create();

        $this->command->getOutput()->writeln("<info>Seeding:</info>   Tags");
        App\Voucher::all()->each(function ($voucher) {
            $faker = Faker::create();
            $tags = App\Tag::all();

            if ($faker->boolean(50)) {  // coin toss whether or not a voucher gets any tags attached
                $voucher->tags()->attach(
                    array_unique($tags->pluck('id')->random(2)->toArray())
                );
            }
        });

        $this->command->getOutput()->writeln("<info>Seeding:</info>   ConsumerTags");
        App\Tag::all()->each(function ($tag) {
            $consumers = App\Consumer::whereNotNull('gdpr_optin_email_date');

            $tag->consumers()->attach(
                array_unique($consumers->pluck('id')->random(rand(1, $consumers->count()))->toArray())
            );
        });
    }
}
