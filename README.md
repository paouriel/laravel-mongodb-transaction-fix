# laravel-mongodb-transaction-fix

This will add a console command that will automatically update: `vendor/jenssegers/mongodb/src/Jenssegers/Mongodb/Connection.php` and include the necessary functions/commands for beginTransaction(). This fixes `Call to a member function beginTransaction() on null`

# Installation
Simply move `MongoDBTransactionFix.php` to `app/Console/Commands`. Create `Commands` folder if does not exist.

# How to use
Run `php artisan fix:transaction`. To revert, run `php artisan fix:transaction --rollback` 
