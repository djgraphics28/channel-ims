# Installation Instructions for School Portal Laravel 12 Project

## Prerequisites
- PHP 8.3 or higher
- Composer
- Node.js and npm
- MySQL or another database supported by Laravel
- Git

## Installation Steps

1. Clone the repository
   ```bash
   git clone https://github.com/djgraphics28/school-portal.git school-portal
   cd school-portal
   ```

2. Install PHP dependencies
   ```bash
   composer install
   ```

3. Create environment file and configure database
   ```bash
   cp .env.example .env
   ```
   Then edit the .env file and change the database name to your desired database name

4. Generate application key
   ```bash
   php artisan key:generate
   ```
5. Migrate and seed 
   ```bash
   php artisan migrate --seed
   ```

6. Install and compile frontend assets
   ```bash
   npm install && npm run dev
   ```

7. Start the development server
   ```bash
   php artisan serve
   ```

