<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SaqScrapingTest extends DuskTestCase
{
    /**
     * A basic browser test example.
     *
     * @return void
     */
    public function testScrapingDataFromSaq()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('https://www.saq.com')
            ->waitFor('.product-title', 10) // Increase the timeout to 10 seconds
            ->script("window.scrollTo(0, document.body.scrollHeight);");

        $productTitles = $browser->elements('.product-title');
        foreach ($productTitles as $title) {
            echo $title->getText() . PHP_EOL;
        }
    });
}

}
