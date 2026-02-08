# Ride-Hailing Backend API

A complete ride-hailing backend system built with Laravel, implementing the full ride flow from estimation to completion.

## Features

### ✅ Ride Estimation
- Calculate distance, ETA, and price based on pickup and dropoff locations
- Uses Haversine formula for accurate distance calculation
- Supports both address strings and coordinates

### ✅ Ride Creation Rules (Strict)
- **Location permission required**: Pickup coordinates must be provided (auto-detected only, no manual pickup)
- **Rate limiting**: Maximum 3 ride requests per 10 minutes per user
- **Single active ride**: Only 1 active ride at a time per user

### ✅ Matching + Driver Assignment
- Automatically assigns the nearest available driver based on location
- Driver offer expires in 15 seconds (TTL)
- If driver doesn't accept in time, system tries the next nearest driver
- Uses queue jobs for asynchronous processing (non-blocking)

### ✅ Ride States
Complete state machine with the following flow:
- `MATCHING` → `DRIVER_ASSIGNED` → `ARRIVED` → `ONGOING` → `COMPLETED`
- State transitions are validated and logged

### ✅ Cancellation
- Free cancellation before driver assignment
- After assignment: cancellation requires a reason and is logged
- Driver availability is restored on cancellation

### ✅ Trip History + Event Logs
- Complete trip history for users
- Comprehensive event logging (request, match, assign, state changes, cancel, etc.)
- All events are timestamped and stored

### ✅ Share Trip
- Public endpoint to share trip status using a unique token
- No authentication required for viewing shared trips

## Tech Stack

- **Framework**: Laravel 10
- **Database**: MySQL/PostgreSQL
- **Cache**: Redis (for driver offers with TTL)
- **Queue**: Laravel Queue (for async matching)
- **Authentication**: Laravel Sanctum
- **Location Services**: Mapbox (geocoding and location services)

## Installation

### Prerequisites

- PHP >= 8.1
- Composer
- MySQL/PostgreSQL
- Redis (for caching and queues)

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd ride-hailing-backend
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure `.env` file**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ride_hailing
   DB_USERNAME=root
   DB_PASSWORD=

   CACHE_DRIVER=redis
   QUEUE_CONNECTION=redis

   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379

   MAPBOX_ACCESS_TOKEN=your_mapbox_access_token_here
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Start queue worker** (required for driver matching)
   ```bash
   php artisan queue:work
   ```

7. **Start the development server**
   ```bash
   php artisan serve
   ```

The API will be available at `http://localhost:8000`

## API Endpoints

### Authentication
All endpoints (except share) require authentication using Laravel Sanctum.

**Get authenticated user:**
```
GET /api/user
Headers: Authorization: Bearer {token}
```

### Ride Estimation

**Estimate ride (distance, ETA, price)**
```
POST /api/rides/estimate
Headers: Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "pickup_location": "123 Main St, City",
  "dropoff_location": "456 Oak Ave, City",
  "pickup_latitude": 33.5731,
  "pickup_longitude": -7.5898,
  "dropoff_latitude": 33.5928,
  "dropoff_longitude": -7.6191
}

Response:
{
  "distance_km": 5.23,
  "duration_min": 15,
  "price": 20.50,
  "pickup_coordinates": [33.5731, -7.5898],
  "dropoff_coordinates": [33.5928, -7.6191]
}
```

### Ride Management

**Create a new ride**
```
POST /api/rides
Headers: Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "pickup_location": "123 Main St, City",
  "dropoff_location": "456 Oak Ave, City",
  "pickup_latitude": 33.5731,
  "pickup_longitude": -7.5898,
  "dropoff_latitude": 33.5928,
  "dropoff_longitude": -7.6191
}

Response: 201 Created
{
  "id": 1,
  "user_id": 1,
  "status": "matching",
  "pickup_location": "123 Main St, City",
  "dropoff_location": "456 Oak Ave, City",
  "distance_km": 5.23,
  "eta_minutes": 15,
  "price": 20.50,
  "share_token": "abc123...",
  ...
}
```

**Get trip history**
```
GET /api/rides/history
Headers: Authorization: Bearer {token}

Response:
{
  "data": [...],
  "current_page": 1,
  "per_page": 20,
  ...
}
```

**Get active ride**
```
GET /api/rides/active
Headers: Authorization: Bearer {token}

Response:
{
  "id": 1,
  "status": "driver_assigned",
  ...
}
```

**Cancel a ride**
```
POST /api/rides/{rideId}/cancel
Headers: Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "reason": "Changed my mind" // Required if driver is assigned
}
```

### Driver Actions

**Accept ride offer**
```
POST /api/rides/{rideId}/accept
Headers: Authorization: Bearer {token}
```

**Mark driver arrived**
```
POST /api/rides/{rideId}/arrived
Headers: Authorization: Bearer {token}
```

**Mark ride as ongoing**
```
POST /api/rides/{rideId}/ongoing
Headers: Authorization: Bearer {token}
```

**Mark ride as completed**
```
POST /api/rides/{rideId}/completed
Headers: Authorization: Bearer {token}
```

**Update driver location** (for nearest driver matching)
```
POST /api/driver/location
Headers: Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "latitude": 33.5731,
  "longitude": -7.5898
}
```

**Update driver availability**
```
POST /api/driver/availability
Headers: Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "is_available": true
}
```

**Get driver's ride history**
```
GET /api/driver/rides/history
Headers: Authorization: Bearer {token}
```

### Share Trip (Public)

**Get shared trip status**
```
GET /api/rides/share/{shareToken}

Response:
{
  "id": 1,
  "status": "ongoing",
  "pickup_location": "123 Main St",
  "dropoff_location": "456 Oak Ave",
  "distance_km": 5.23,
  "eta_minutes": 15,
  "price": 20.50,
  "driver": {
    "name": "John Doe"
  },
  "events": [...]
}
```

## Database Schema

### Users Table
- `id`
- `name`
- `email`
- `password`
- `is_driver` (boolean)
- `is_available` (boolean)
- `latitude` (decimal)
- `longitude` (decimal)
- `timestamps`

### Rides Table
- `id`
- `user_id` (foreign key)
- `driver_id` (foreign key, nullable)
- `pickup_location` (string)
- `dropoff_location` (string)
- `pickup_latitude` (decimal)
- `pickup_longitude` (decimal)
- `dropoff_latitude` (decimal)
- `dropoff_longitude` (decimal)
- `status` (enum: matching, driver_assigned, arrived, ongoing, completed, cancelled)
- `distance_km` (decimal)
- `eta_minutes` (integer)
- `price` (decimal)
- `share_token` (string, unique)
- `assigned_at` (timestamp)
- `started_at` (timestamp)
- `completed_at` (timestamp)
- `timestamps`

### Ride Events Table
- `id`
- `ride_id` (foreign key)
- `event` (string: request, match, assign, state_change, cancel)
- `note` (text, nullable)
- `timestamps`

## Architecture & Design Decisions

### Domain-Driven Design
The codebase follows Domain-Driven Design principles:
- **Domain Services**: Business logic is encapsulated in domain services (`app/Domain/`)
- **Enums**: Ride status is managed using PHP 8.1 enums
- **Separation of Concerns**: Controllers handle HTTP, services handle business logic

### Queue-Based Matching
- Driver matching runs asynchronously using Laravel queues
- Prevents blocking the HTTP request
- Allows for better scalability

### Caching Strategy
- Driver offers stored in Redis with 15-second TTL
- Automatic expiration handles offer timeout
- Efficient driver lookup and offer management

### Location Handling
- **Mapbox Integration**: Uses Mapbox Geocoding API for address-to-coordinates conversion
- Uses Haversine formula for distance calculation
- Supports both address strings and coordinates
- Automatic fallback to mock coordinates if Mapbox API is unavailable (for development)

## Testing

Run the test suite:
```bash
php artisan test
```

## Production Considerations

1. **Mapbox Configuration**: Ensure `MAPBOX_ACCESS_TOKEN` is set in production environment
2. **Queue Workers**: Use supervisor or similar to manage queue workers
3. **Caching**: Ensure Redis is properly configured for production
4. **Database Indexing**: Add indexes on frequently queried fields
5. **Rate Limiting**: Configure appropriate rate limits for production
6. **Monitoring**: Set up logging and monitoring for ride events

## Notes & Tradeoffs

### Approach
- **Strict validation**: All ride creation rules are enforced at the service level
- **Event-driven logging**: All key events are logged for audit trail
- **Async processing**: Driver matching is non-blocking for better UX
- **Public sharing**: Share tokens allow trip tracking without authentication

### Tradeoffs
- **Geocoding**: Currently uses mock coordinates; production needs real API
- **Distance calculation**: Uses Haversine (great for short distances); for longer distances, consider more sophisticated algorithms
- **Driver matching**: Sequential matching (one at a time); could be optimized for parallel offers
- **Queue processing**: Uses database queue by default; Redis queue recommended for production

## License

MIT License

## Author

Built as part of a backend developer challenge.
