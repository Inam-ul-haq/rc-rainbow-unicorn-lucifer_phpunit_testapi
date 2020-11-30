# RC - Rainbow Unicorn

## Installation
Having downloaded the source code, you'll need to rename `.env.example` to `.env` then generate an App Key with `php artisan key:generate` and a JWT Secret with `php artisan jwt:secret`.

Add a value to the .env file called SITE_SOURCE_VALUE, and set it to a relevant value. This is used in the source field when an event for example is added by an action done by an authenticated user. Note that requests via api keys have their own source value.

Now you can create the database tables with `php artisan migrate`.

Following that, setup Passport by running `php artisan passport:install`.

Having done that, you will want to use one of the two provided database seeds to seed the data.

The first one is for production sites:
```bash
php artisan db:seed
```

The second option is for test/development sites. This seed will populate most tables with dummy data. It's worth noting that data might not make sense throughout the site in this situation! For example, on a live site, if a coupon is restricted to a particular consumer id, then the redeemed_by value for that coupon will always be that restricted consumer id. With the test data, that consistency isn't present - the Coupon factory just (optionally) plucks a random consumer id for both of those fields.

Still though, use this (and feel free to improve it) - it'll make life a lot easier for you when developing on the site!
```bash
php artisan db:seed --class=TestDataDatabaseSeeder
```

The system uses Scout with TNTsearch for searching. To enable this, you'll need to add the following to your .env file:
```
SCOUT_DRIVER=tntsearch
TNTSEARCH_FUZZINESS=auto
```

If you've used the TestDataDatabaseSeeder, you'll need to import the created objects into Scout:
```
php artisan scout:import "App\Consumer"
php artisan scout:import "App\Partner"
php artisan scout:import "App\Referrer"
```

To get the queues working, update or edit the QUEUE\_CONNECTION in your .env file, and run a queue listener using artisan:
```
QUEUE_CONNECTION=database
```
then
```
php artisan queue:listen
```

Assets like barcodes are written out to disk to be available on a separate site for visitors to access. Set the following in your .env file to support this:
```
PUBLIC_WEB_ADDRESS=http://lucifer_public.rcuk/
PUBLIC_SITE_LINK_DIR=public_website
```
replacing the address and directory name as appropriate. PUBLIC\_SITE\_LINK\_DIR is the name of a directory in storage\_dir(), in which assets will be written. PUBLIC\_WEB\_ADDRESS should be the address of a site using PUBLIC\_SITE\_LINK\_DIR as its document root (such that, with the example settings above, a file written to storage/public\_website/barcodes/1234.png would be accessible at http://lucifer\_public.rcuk/1234.png).


To install frontend dependencies run:
```bash
npm install
```

You can then start up the frontend development files with:
```bash
npm run watch
```
