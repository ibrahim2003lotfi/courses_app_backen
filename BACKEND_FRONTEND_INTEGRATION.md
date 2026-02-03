# Backend-Frontend Integration Guide

## Overview
This document explains how the Laravel backend API is linked to the Flutter mobile app.

## API Base URL
The Flutter app connects to: `http://127.0.0.1:8000/api`

**Important:** Update this in `mobile/courses_app/lib/config/api.dart` when deploying to production.

## Services Created

### 1. AuthService (`lib/services/auth_service.dart`)
**Fixed and Enhanced:**
- ✅ Login now uses `login` field (can be email or phone)
- ✅ Added `register()` method
- ✅ Added `verify()` method for account verification
- ✅ Added `resendVerification()` method
- ✅ Logout now calls backend API

**Endpoints Used:**
- `POST /api/login` - Login with email/phone
- `POST /api/register` - Register new user
- `POST /api/verify` - Verify account with code
- `POST /api/resend-verification` - Resend verification code
- `POST /api/logout` - Logout user

### 2. PaymentService (`lib/services/payment_service.dart`)
**Created:**
- ✅ `initiatePayment()` - Start payment for a course (uses course slug)
- ✅ `confirmPayment()` - Confirm payment with receipt image
- ✅ `getPaymentStatus()` - Check payment status
- ✅ `getPaymentHistory()` - Get user's payment history

**Endpoints Used:**
- `POST /api/courses/{slug}/payment` - Initiate payment
- `POST /api/payments/confirm` - Confirm payment (with receipt image)
- `GET /api/payments/{orderId}/status` - Get payment status
- `GET /api/payments/history` - Get payment history

### 3. ReviewService (`lib/services/review_service.dart`)
**Created:**
- ✅ `rateCourse()` - Rate and review a course
- ✅ `getMyRating()` - Get user's rating for a course
- ✅ `deleteRating()` - Delete user's rating
- ✅ `getUserRatings()` - Get all user's ratings
- ✅ `getCourseRating()` - Get public course rating

**Endpoints Used:**
- `POST /api/courses/{courseId}/rate` - Rate a course
- `GET /api/courses/{courseId}/my-rating` - Get my rating
- `DELETE /api/courses/{courseId}/my-rating` - Delete rating
- `GET /api/my-ratings` - Get all my ratings
- `GET /api/courses/{courseId}/rating` - Get course rating (public)

### 4. SearchService (`lib/services/search_service.dart`)
**Created:**
- ✅ `search()` - Search courses with filters
- ✅ `getSuggestions()` - Get search suggestions

**Endpoints Used:**
- `GET /api/v1/search` - Search courses
- `GET /api/v1/search/suggestions` - Get suggestions

### 5. CourseApi (`lib/services/course_api.dart`)
**Enhanced:**
- ✅ Added section management methods
- ✅ Added lesson management methods
- ✅ Added `getLessonStream()` for video streaming

**New Methods:**
- `getCourseSections()` - Get course sections
- `createSection()` - Create section
- `updateSection()` - Update section
- `deleteSection()` - Delete section
- `getLessons()` - Get lessons for section
- `createLesson()` - Create lesson
- `updateLesson()` - Update lesson
- `deleteLesson()` - Delete lesson
- `getLessonStream()` - Get video stream URL

### 6. ApiClient (`lib/services/api_client.dart`)
**Enhanced:**
- ✅ Added `put()` method
- ✅ Added `delete()` method
- ✅ Fixed authorization header to be optional (for public endpoints)

## Integration Status

### ✅ Completed
1. Authentication (login, register, verify)
2. Payment flow (initiate, confirm, status)
3. Course reviews and ratings
4. Search functionality
5. Course management (sections, lessons)
6. Video streaming

### ⚠️ Needs Implementation
1. **Payment Flow Integration**: Update `PaymentBottomSheet` widget to use `PaymentService`
   - Location: `lib/main_pages/courses/presentation/widgets/courses_details_widgets.dart`
   - Replace mock payment with real API calls

2. **Course Enrollment**: Update `CourseManagementBloc` to fetch real enrollments
   - Location: `lib/bloc/course_management_bloc.dart`
   - Add API call to get user's enrolled courses

3. **Review Integration**: Link review pages to `ReviewService`
   - Location: `lib/main_pages/reviews/presentation/pages/add_review_page.dart`

4. **Search Integration**: Link search page to `SearchService`
   - Location: `lib/main_pages/search/presentation/pages/search_page.dart`

## How to Test

### 1. Start Backend
```bash
cd backend
php artisan serve
# Backend runs on http://127.0.0.1:8000
```

### 2. Update API URL (if needed)
Edit `mobile/courses_app/lib/config/api.dart`:
```dart
static const String baseUrl = "http://127.0.0.1:8000/api";
```

### 3. Test Authentication
- Register a new user
- Verify account with code
- Login with email/phone

### 4. Test Payment Flow
- Browse courses
- Click "اشترك الآن" (Enroll Now)
- Select payment method
- Complete payment flow

### 5. Test Reviews
- Rate a course
- View course ratings
- Manage your ratings

## Important Notes

1. **Course Slug**: Payment API uses course `slug`, not `id`. Make sure course objects include slug field.

2. **Free Courses**: Free courses should skip payment and directly enroll. Update `_enrollFreeCourse()` method.

3. **Receipt Upload**: Payment confirmation requires receipt image upload. Use `FilePicker` or `ImagePicker`.

4. **Error Handling**: All services return error responses. Handle errors appropriately in UI.

5. **Token Storage**: Tokens are stored securely using `FlutterSecureStorage`.

## Next Steps

1. Update `PaymentBottomSheet` to use `PaymentService`
2. Update `CourseManagementBloc` to fetch real enrollments
3. Link review pages to `ReviewService`
4. Link search page to `SearchService`
5. Add error handling and loading states
6. Test all flows end-to-end

## API Response Formats

### Success Response
```json
{
  "message": "Success message",
  "data": {...}
}
```

### Error Response
```json
{
  "message": "Error message",
  "error": "Error details"
}
```

## Support

If you encounter issues:
1. Check backend logs: `backend/storage/logs/laravel.log`
2. Verify API endpoints in `backend/routes/api.php`
3. Check Flutter console for API errors
4. Verify authentication token is being sent









