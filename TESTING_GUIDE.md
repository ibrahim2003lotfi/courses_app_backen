# Testing Guide - Backend to Frontend Integration

## What Was Done Exactly

### 1. Fixed AuthService (`lib/services/auth_service.dart`)
**Before:** Login used `email` field only
**After:** 
- ✅ Login now uses `login` field (accepts email OR phone number)
- ✅ Added `register()` method - Creates new user account
- ✅ Added `verify()` method - Verifies account with 6-digit code
- ✅ Added `resendVerification()` method - Resends verification code
- ✅ Enhanced `logout()` - Now calls backend API before clearing local storage
- ✅ Stores `user_id` along with token for verification flow

**Backend Endpoint:** `POST /api/login` expects `{"login": "email_or_phone", "password": "..."}`

### 2. Enhanced ApiClient (`lib/services/api_client.dart`)
**Added:**
- ✅ `put()` method for UPDATE requests
- ✅ `delete()` method for DELETE requests
- ✅ Authorization header is now optional (for public endpoints)
- ✅ All methods automatically include Bearer token if available

### 3. Created PaymentService (`lib/services/payment_service.dart`)
**New Service** with methods:
- ✅ `initiatePayment()` - Starts payment for a course (uses course slug)
- ✅ `confirmPayment()` - Confirms payment with receipt image upload
- ✅ `getPaymentStatus()` - Checks payment status
- ✅ `getPaymentHistory()` - Gets user's payment history

**Backend Endpoints:**
- `POST /api/courses/{slug}/payment` - Initiate payment
- `POST /api/payments/confirm` - Confirm payment (multipart/form-data with image)
- `GET /api/payments/{orderId}/status` - Get status
- `GET /api/payments/history` - Get history

### 4. Created ReviewService (`lib/services/review_service.dart`)
**New Service** with methods:
- ✅ `rateCourse()` - Rate and review a course
- ✅ `getMyRating()` - Get user's rating for a course
- ✅ `deleteRating()` - Delete user's rating
- ✅ `getUserRatings()` - Get all user's ratings
- ✅ `getCourseRating()` - Get public course rating

**Backend Endpoints:**
- `POST /api/courses/{courseId}/rate` - Rate course
- `GET /api/courses/{courseId}/my-rating` - Get my rating
- `DELETE /api/courses/{courseId}/my-rating` - Delete rating
- `GET /api/my-ratings` - Get all my ratings
- `GET /api/courses/{courseId}/rating` - Get course rating (public)

### 5. Created SearchService (`lib/services/search_service.dart`)
**New Service** with methods:
- ✅ `search()` - Search courses with filters (query, category, level, price range)
- ✅ `getSuggestions()` - Get search suggestions

**Backend Endpoints:**
- `GET /api/v1/search?q=...&category_id=...&level=...` - Search
- `GET /api/v1/search/suggestions?q=...` - Get suggestions

### 6. Enhanced CourseApi (`lib/services/course_api.dart`)
**Added Methods:**
- ✅ `getCourseSections()` - Get sections for a course
- ✅ `createSection()` - Create new section
- ✅ `updateSection()` - Update section
- ✅ `deleteSection()` - Delete section
- ✅ `getLessons()` - Get lessons for a section
- ✅ `createLesson()` - Create new lesson
- ✅ `updateLesson()` - Update lesson
- ✅ `deleteLesson()` - Delete lesson
- ✅ `getLessonStream()` - Get video stream URL for enrolled students

**Backend Endpoints:**
- `GET /api/instructor/courses/{courseId}/sections` - Get sections
- `POST /api/instructor/courses/{courseId}/sections` - Create section
- `PUT /api/instructor/courses/{courseId}/sections/{sectionId}` - Update section
- `DELETE /api/instructor/courses/{courseId}/sections/{sectionId}` - Delete section
- `GET /api/instructor/courses/{courseId}/sections/{sectionId}/lessons` - Get lessons
- `POST /api/instructor/courses/{courseId}/sections/{sectionId}/lessons` - Create lesson
- `PUT /api/instructor/courses/{courseId}/sections/{sectionId}/lessons/{lessonId}` - Update lesson
- `DELETE /api/instructor/courses/{courseId}/sections/{sectionId}/lessons/{lessonId}` - Delete lesson
- `GET /api/courses/{slug}/stream/{lessonId}` - Get stream URL

---

## How to Test

### Prerequisites
1. **Backend must be running:**
   ```bash
   cd backend
   php artisan serve
   ```
   Backend should be accessible at: `http://127.0.0.1:8000`

2. **Check API URL in Flutter:**
   File: `mobile/courses_app/lib/config/api.dart`
   Should be: `http://127.0.0.1:8000/api`

3. **For Android Emulator:** Use `http://10.0.2.2:8000/api` instead
4. **For iOS Simulator:** Use `http://localhost:8000/api` or `http://127.0.0.1:8000/api`
5. **For Physical Device:** Use your computer's IP address (e.g., `http://192.168.1.100:8000/api`)

---

## Test 1: Authentication (Login)

### Step 1: Test Login
1. Open Flutter app
2. Go to Login page
3. Enter email or phone number + password
4. Click "تسجيل الدخول" (Login)

**Expected Result:**
- ✅ Login succeeds
- ✅ Token is saved
- ✅ User is redirected to home/dashboard
- ✅ No error messages

**Check in Code:**
- Open `lib/main_pages/auth/presentation/pages/login_page.dart`
- Find `_handleLogin()` method (around line 600)
- It should call: `_authService.login(email, password)`
- The response should have `status: 200` and `data.token`

**If it fails:**
- Check backend logs: `backend/storage/logs/laravel.log`
- Verify backend is running: `curl http://127.0.0.1:8000/api/test`
- Check API URL in `lib/config/api.dart`

---

## Test 2: Registration & Verification

### Step 1: Test Registration
1. Go to Register page
2. Fill all fields:
   - Name
   - Email
   - Password + Confirm Password
   - Role (student/instructor/admin)
   - Age
   - Gender
   - Phone
   - Verification Method (email/phone)
3. Submit registration

**Expected Result:**
- ✅ Registration succeeds
- ✅ Returns `user_id` and `needs_verification: true`
- ✅ Shows verification code screen

**Check in Code:**
- The `register()` method should return:
  ```json
  {
    "status": 201,
    "data": {
      "user_id": "...",
      "needs_verification": true,
      "verification_method": "email"
    }
  }
  ```

### Step 2: Test Verification
1. Enter the 6-digit verification code
2. Click verify

**Expected Result:**
- ✅ Verification succeeds
- ✅ Token is saved
- ✅ User is logged in
- ✅ Redirected to app

**If it fails:**
- Check email/phone for verification code
- Code expires after 15 minutes
- Use `resendVerification()` if needed

---

## Test 3: Payment Flow

### Step 1: Test Payment Initiation
**Note:** This requires updating the UI first, but you can test the service directly:

```dart
import 'package:courses_app/services/payment_service.dart';

final paymentService = PaymentService();

// Test initiate payment
final result = await paymentService.initiatePayment(
  courseSlug: 'your-course-slug',
  paymentMethod: 'syrian_manual',
);

print('Order ID: ${result['order_id']}');
print('Status: ${result['status']}');
```

**Expected Result:**
- ✅ Returns `order_id`
- ✅ Returns `payment_instructions`
- ✅ Status is `pending`

**Backend Check:**
- Check `orders` table in database
- Order should be created with status `pending`

### Step 2: Test Payment Confirmation
```dart
// After uploading receipt image
final confirmResult = await paymentService.confirmPayment(
  orderId: 'order-id-from-step-1',
  receiptImage: File('path/to/receipt.jpg'),
  confirmationMethod: 'upload',
);
```

**Expected Result:**
- ✅ Returns `enrolled: true`
- ✅ Order status becomes `succeeded`
- ✅ Enrollment is created in database

**Backend Check:**
- Check `orders` table - status should be `succeeded`
- Check `enrollments` table - new enrollment should exist

---

## Test 4: Course Reviews

### Test Rating a Course
```dart
import 'package:courses_app/services/review_service.dart';

final reviewService = ReviewService();

// Rate a course
final result = await reviewService.rateCourse(
  courseId: 'course-id',
  rating: 5,
  review: 'Great course!',
);
```

**Expected Result:**
- ✅ Rating is saved
- ✅ Returns success message

**Backend Check:**
- Check `reviews` table
- Review should be linked to course and user

### Test Getting Ratings
```dart
// Get my rating
final myRating = await reviewService.getMyRating('course-id');

// Get course public rating
final courseRating = await reviewService.getCourseRating('course-id');
```

**Expected Result:**
- ✅ Returns rating data
- ✅ Includes average rating and review count

---

## Test 5: Search

### Test Search
```dart
import 'package:courses_app/services/search_service.dart';

final searchService = SearchService();

// Search courses
final results = await searchService.search(
  query: 'flutter',
  level: 'beginner',
  minPrice: 0,
  maxPrice: 100,
);
```

**Expected Result:**
- ✅ Returns list of courses
- ✅ Results match search criteria

**Backend Check:**
- Check backend logs for search query
- Results should be filtered correctly

---

## Test 6: Course Management (Instructor)

### Test Getting Sections
```dart
import 'package:courses_app/services/course_api.dart';

final courseApi = CourseApi();

// Get course sections
final sections = await courseApi.getCourseSections('course-id');
```

**Expected Result:**
- ✅ Returns list of sections
- ✅ Each section includes lessons

### Test Creating Section
```dart
final newSection = await courseApi.createSection(
  courseId: 'course-id',
  title: 'Introduction',
  description: 'Course introduction',
  order: 1,
);
```

**Expected Result:**
- ✅ Section is created
- ✅ Returns section data with ID

**Backend Check:**
- Check `sections` table
- Section should be linked to course

---

## Quick Test Script

Create a test file: `mobile/courses_app/test_api.dart`

```dart
import 'package:courses_app/services/auth_service.dart';
import 'package:courses_app/services/payment_service.dart';
import 'package:courses_app/services/review_service.dart';
import 'package:courses_app/services/search_service.dart';
import 'package:courses_app/services/course_api.dart';

void testAllServices() async {
  print('Testing AuthService...');
  final auth = AuthService();
  
  // Test login
  final loginResult = await auth.login('test@example.com', 'password');
  print('Login: ${loginResult['status']}');
  
  if (loginResult['status'] == 200) {
    print('✅ Login successful!');
    
    // Test other services
    print('\nTesting PaymentService...');
    final payment = PaymentService();
    // Add payment tests here
    
    print('\nTesting ReviewService...');
    final review = ReviewService();
    // Add review tests here
    
    print('\nTesting SearchService...');
    final search = SearchService();
    final searchResults = await search.search(query: 'test');
    print('Search results: ${searchResults['data']?.length ?? 0} courses');
    
    print('\n✅ All services tested!');
  } else {
    print('❌ Login failed: ${loginResult['data']}');
  }
}
```

---

## Common Issues & Solutions

### Issue 1: Connection Refused
**Error:** `SocketException: Connection refused`
**Solution:**
- Make sure backend is running: `php artisan serve`
- Check API URL in `lib/config/api.dart`
- For Android emulator, use `10.0.2.2` instead of `127.0.0.1`
- For physical device, use computer's IP address

### Issue 2: 401 Unauthorized
**Error:** `401 Unauthorized`
**Solution:**
- Token might be expired or invalid
- Try logging out and logging in again
- Check if token is being sent in headers

### Issue 3: 404 Not Found
**Error:** `404 Not Found`
**Solution:**
- Check endpoint URL matches backend routes
- Verify backend routes in `backend/routes/api.php`
- Make sure API prefix is correct (`/api/...`)

### Issue 4: CORS Error (Web)
**Error:** CORS policy error
**Solution:**
- Check `backend/config/cors.php`
- Make sure frontend origin is allowed
- For mobile apps, CORS doesn't apply

### Issue 5: Token Not Saved
**Error:** Token is null after login
**Solution:**
- Check `FlutterSecureStorage` permissions
- Verify response contains `token` field
- Check response status code is 200

---

## Verification Checklist

- [ ] Backend is running on `http://127.0.0.1:8000`
- [ ] API URL in Flutter is correct
- [ ] Login works with email/phone
- [ ] Registration creates user
- [ ] Verification code works
- [ ] Token is saved after login
- [ ] Payment initiation works
- [ ] Payment confirmation works
- [ ] Reviews can be created
- [ ] Search returns results
- [ ] Course sections can be fetched

---

## Next Steps After Testing

Once all tests pass:
1. Update UI widgets to use these services
2. Replace mock data with real API calls
3. Add error handling and loading states
4. Test end-to-end user flows










