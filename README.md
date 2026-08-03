# Product Management System

## Overview

The Product Management System is a PHP and MySQL-based web application designed to manage product records efficiently. It demonstrates secure backend development practices, database management, role-based access control, and CRUD (Create, Read, Update, Delete) operations.

This project was developed as a reusable inventory management component that can be integrated into larger business management systems.

## Features

### Product Management
- Add new products with:
  - Product name
  - Price
  - Stock quantity
  - Restock threshold
  - Category and brand information

- View available products
- Search products by name
- Edit product information
- Monitor stock levels

### Inventory Features
- Automatic low-stock identification
- Stock threshold monitoring
- Product activation/deactivation

### User Roles

The system implements role-based access:

**Administrator and Procurement**
- Add products
- Edit products
- Permanently delete products
- Manage product information

**Employee**
- View products and sell via POS. Employees cant access this file

## Security Implementation

This project applies several secure coding practices:

- Session-based authentication
- Role-based authorization
- CSRF token protection
- Prepared SQL statements using PDO
- Input validation
- Output escaping to reduce XSS risks

## Technologies Used

- PHP 8
- MySQL
- PDO
- HTML5
- CSS3
- JavaScript

## Project Structure

```
product-management/
│
├── product.php          # Main product management module
├── db.php               # Database connection
├── assets/              # Styles and scripts
├── database.sql         # Sample database structure
└── README.md
```

## Installation

### Requirements

- PHP 8 or later
- MySQL database
- Apache server (XAMPP/WAMP/LAMP)

### Setup

1. Clone the repository:

```
git clone https://github.com/yourusername/product-management.git
```

2. Create a MySQL database.

3. Import the provided database file.

4. Update database connection settings.

5. Start your local server and open the application.

## Database Design

The system uses a relational database structure to store:

- Products
- Categories
- Brands
- Stock information
- User roles

## Screenshots

(Add screenshots here)

Example:

- Product listing page
- Add product form
- Low stock notification

 Purpose

This project was created to demonstrate practical skills in:

- Backend web development
- Database design
- Secure PHP programming
- Inventory management systems
- Building reusable software components

 Future Improvements

Possible enhancements:

- Product image uploads
- Barcode scanning
- Advanced reporting
- Pagination
- API integration
- Automated stock alerts

## License

This project is published for educational and portfolio purposes.
