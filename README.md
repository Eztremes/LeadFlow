\# LeadFlow — Large CSV Lead Import System



A full-stack lead import application built with React, Laravel, and MySQL. LeadFlow processes large CSV files asynchronously, validates lead information, detects duplicates, tracks import progress, and generates downloadable failure reports.



\## Features



\- Upload CSV files through a React dashboard

\- Asynchronous CSV processing using Laravel database queues

\- Memory-efficient, row-by-row CSV reading

\- Batch processing in groups of 500 records

\- Validation of name, email, phone, and company

\- Duplicate email detection

\- Import status and progress tracking

\- Failed-record CSV generation with validation reasons

\- Email notifications with failed-record CSV attachments

\- Database transactions and checkpoint-based retry support

\- Queue uniqueness and database locking to reduce duplicate processing



\## Tech Stack



| Frontend | Backend | Database \& Infrastructure |

|---|---|---|

| React, Vite, Tailwind CSS, Axios | Laravel 13, PHP 8.3 | MySQL, Laravel Database Queue, SMTP |



\## Architecture



1\. A user uploads a CSV file and enters a notification email.

2\. Laravel validates the upload, stores the file, and creates an import record.

3\. A queued job streams the CSV and processes records in batches.

4\. Valid records are inserted into MySQL; invalid and duplicate records are recorded with failure reasons.

5\. The dashboard polls the API for progress updates.

6\. After processing, Laravel generates a failed-record CSV when necessary.

7\. A separate queued email job sends the failure report to the notification address.



\## CSV Format



The CSV must contain these columns:



`name,email,phone,company`



Example:



```csv

name,email,phone,company

Aarav,aarav@example.com,9876543210,TechNova

Priya,priya@example.com,9876543211,CloudWorks

```



\## Project Structure



```text

LeadFlow/

├── frontend/     React application

├── backend/      Laravel API and queue jobs

└── README.md

```



\## Local Installation



\### Requirements



\- PHP 8.3 or compatible Laravel-supported version

\- Composer

\- Node.js and npm

\- MySQL

\- SMTP credentials for email delivery



\### Backend



```bash

cd backend

composer install

cp .env.example .env

php artisan key:generate

```



Configure the database, queue, cache, and SMTP settings in `backend/.env`.



Create the configured MySQL database, then run:



```bash

php artisan migrate

php artisan serve

```



Start a separate terminal for the queue worker:



```bash

php artisan queue:work --timeout=3600 --tries=3

```



For long-running imports, configure the database queue's `retry\_after` value to exceed the worker timeout.



\### Frontend



```bash

cd frontend

npm ci

cp .env.example .env

npm run dev

```



Set `VITE\_API\_BASE\_URL` to the Laravel server URL, without `/api`.



\## Testing



Run the Laravel automated tests:



```bash

cd backend

php artisan test

```



\*\*Latest local results:\*\* 9 tests passed, 32 assertions.



A 100,000-record CSV was also successfully processed during local stress testing. Performance and processing duration depend on the machine, database, and hosting environment.



\## API Endpoints



| Method | Endpoint | Description |

|---|---|---|

| POST | `/api/imports` | Upload CSV and notification email |

| GET | `/api/imports/{id}` | Retrieve import status and progress |

| GET | `/api/imports/{id}/failed-csv` | Download the failed-record report |



The upload request uses multipart form fields `file` and `email`.



\## Deployment Notes



The application has been tested locally. A cloud deployment requires a persistent queue worker, MySQL database, suitable file storage accessible to the API and worker, and an SMTP provider configured for external delivery.



For production use, authentication, authorization, upload restrictions, rate limiting, and protection of lead information should be implemented.



\## Author



Jatin Thakur



Developed as a full-stack technical assignment and portfolio project.