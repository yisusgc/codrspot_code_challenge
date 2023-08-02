# CODRSPOT Code Challenge

### Build the application
This application runs on the Laravel framework, follow these steps to install all the required libraries:
1. In the root project folder, run `composer install` to install the Laravel required libraries
1. Link the storage folder: `php artisan storage:link`

### Considerations
1. Make sure the files that you want to use are under `storage/app` folder, ex: `storage/app/file.txt`

### Run the assignment
1. Once you have the files that you are going to use, run the following command: `php artisan DriverToShipmentAssignment addresses.txt names.txt` where:
	- `addresses.txt` points to the file that contains the shipment addresses
	- `namex.txt` points to the file that contains the driver's names
2. Get the output
~~~
Addresses: [...]
Drivers: [...]
Costs Matrix: [...]
Solution indexes: [...]
Driver "X" should take "X1" with a SS of N
Driver "Y" should take "X2" with a SS of N
Driver "Z" should take "X3" with a SS of N
...
~~~


