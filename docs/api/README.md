# TPT Government Platform - API Documentation

## Overview

The TPT Government Platform provides a comprehensive REST API for government service management, built with modern standards and enterprise-grade features.

## Table of Contents

- [Authentication](#authentication)
- [API Versioning](#api-versioning)
- [Rate Limiting](#rate-limiting)
- [Building Consents API](#building-consents-api)
- [Error Handling](#error-handling)
- [Response Format](#response-format)
- [Pagination](#pagination)
- [Filtering](#filtering)
- [Sorting](#sorting)

## Authentication

The API uses JWT (JSON Web Token) based authentication with role-based access control.

### Headers Required

```http
Authorization: Bearer <jwt_token>
Content-Type: application/json
```

### Authentication Endpoints

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "user@example.com",
  "password": "password",
  "remember_me": false
}
```

#### Refresh Token
```http
POST /api/auth/refresh
Authorization: Bearer <refresh_token>
```

#### Logout
```http
POST /api/auth/logout
Authorization: Bearer <jwt_token>
```

## API Versioning

The API supports versioning through URL path versioning.

### Current Versions
- **v1** (Current stable version)
- **v2** (Latest with enhanced features)

### Version Specification

```http
GET /api/v1/building-consents
GET /api/v2/building-consents
```

### Backward Compatibility

- All v1 endpoints remain functional
- New features are added to v2
- Breaking changes are communicated 6 months in advance
- Migration guides provided for major version updates

## Rate Limiting

API requests are rate limited to ensure fair usage and system stability.

### Limits

| User Type | Requests per Minute | Requests per Hour |
|-----------|-------------------|-------------------|
| Anonymous | 60 | 1000 |
| Registered | 300 | 5000 |
| Premium | 1000 | 20000 |
| Admin | Unlimited | Unlimited |

### Rate Limit Headers

```http
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 299
X-RateLimit-Reset: 1609459200
X-RateLimit-Retry-After: 60
```

### Rate Limit Exceeded Response

```json
{
  "error": "Rate limit exceeded",
  "message": "Too many requests. Please try again later.",
  "retry_after": 60
}
```

## Building Consents API

### Applications

#### List Building Consent Applications

```http
GET /api/v2/building-consents
Authorization: Bearer <token>
```

**Query Parameters:**
- `status` (string): Filter by status (draft, submitted, approved, rejected)
- `consent_type` (string): Filter by consent type
- `owner_id` (integer): Filter by owner ID
- `date_from` (string): Filter from date (YYYY-MM-DD)
- `date_to` (string): Filter to date (YYYY-MM-DD)
- `limit` (integer): Number of results per page (default: 20, max: 100)
- `offset` (integer): Pagination offset (default: 0)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "application_id": "BC2024000001",
      "project_name": "Residential Extension",
      "project_type": "addition",
      "property_address": "123 Main Street, Auckland",
      "consent_type": "full",
      "estimated_cost": 150000.00,
      "status": "approved",
      "lodgement_date": "2024-01-15T10:30:00Z",
      "consent_number": "BCN2024000001",
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "count": 1,
  "total": 1,
  "timestamp": "2024-01-15T10:30:00Z"
}
```

#### Create Building Consent Application

```http
POST /api/v2/building-consents
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**
```json
{
  "project_name": "New Residential Construction",
  "project_type": "new_construction",
  "property_address": "456 Oak Avenue, Wellington",
  "property_type": "residential",
  "building_consent_type": "full",
  "estimated_cost": 500000.00,
  "floor_area": 250.5,
  "storeys": 2,
  "architect_id": 123,
  "contractor_id": 456,
  "documents": {
    "site_plan": "site_plan_001.pdf",
    "floor_plans": "floor_plans_001.pdf",
    "elevations": "elevations_001.pdf"
  },
  "notes": "Two-storey residential dwelling with garage"
}
```

**Response:**
```json
{
  "success": true,
  "application_id": "BC2024000002",
  "consent_type": "Full Building Consent",
  "processing_deadline": "2024-02-14T10:30:00Z",
  "requirements": [
    "site_plan",
    "floor_plans",
    "elevations",
    "specifications"
  ],
  "message": "Application created successfully"
}
```

#### Get Building Consent Application

```http
GET /api/v2/building-consents/{application_id}
Authorization: Bearer <token>
```

#### Update Building Consent Application

```http
PUT /api/v2/building-consents/{application_id}
Authorization: Bearer <token>
Content-Type: application/json
```

#### Submit Building Consent Application

```http
POST /api/v2/building-consents/{application_id}/submit
Authorization: Bearer <token>
```

#### Review Building Consent Application

```http
POST /api/v2/building-consents/{application_id}/review
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**
```json
{
  "notes": "Application requires additional structural calculations"
}
```

#### Approve Building Consent Application

```http
POST /api/v2/building-consents/{application_id}/approve
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**
```json
{
  "conditions": [
    "Building must comply with earthquake standards",
    "Noise control measures required"
  ],
  "notes": "Approved with conditions"
}
```

#### Reject Building Consent Application

```http
POST /api/v2/building-consents/{application_id}/reject
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**
```json
{
  "reason": "Application does not meet building code requirements"
}
```

### Inspections

#### List Inspections

```http
GET /api/v2/building-inspections
Authorization: Bearer <token>
```

**Query Parameters:**
- `application_id` (string): Filter by application ID
- `status` (string): Filter by status
- `inspection_type` (string): Filter by inspection type
- `date_from` (string): Filter from date
- `date_to` (string): Filter to date

#### Schedule Inspection

```http
POST /api/v2/building-inspections
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**
```json
{
  "application_id": "BC2024000001",
  "inspection_type": "foundation",
  "preferred_date": "2024-02-01",
  "preferred_time": "10:00",
  "special_requirements": "Access required to rear yard"
}
```

#### Complete Inspection

```http
PUT /api/v2/building-inspections/{inspection_id}/complete
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**
```json
{
  "result": "pass",
  "findings": [
    "Foundation depth meets specifications",
    "Reinforcement properly installed"
  ],
  "recommendations": "Proceed with framing inspection",
  "follow_up_required": false
}
```

### Certificates

#### List Certificates

```http
GET /api/v2/building-certificates
Authorization: Bearer <token>
```

#### Issue Certificate

```http
POST /api/v2/building-consents/{application_id}/certificate
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**
```json
{
  "certificate_type": "code_compliance",
  "issued_by": 123,
  "conditions": [
    "Annual inspection required",
    "Maintenance plan to be submitted"
  ],
  "limitations": "Valid for 10 years from issue date"
}
```

### Fees

#### List Fees

```http
GET /api/v2/building-fees
Authorization: Bearer <token>
```

#### Process Fee Payment

```http
POST /api/v2/building-fees/{invoice_number}/pay
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**
```json
{
  "method": "credit_card",
  "card_number": "4111111111111111",
  "expiry_month": "12",
  "expiry_year": "2025",
  "cvv": "123"
}
```

### Compliance

#### List Compliance Requirements

```http
GET /api/v2/building-compliance
Authorization: Bearer <token>
```

#### Update Compliance Status

```http
PUT /api/v2/building-compliance/{compliance_id}
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**
```json
{
  "status": "completed",
  "evidence": [
    "inspection_report_001.pdf",
    "certificate_of_compliance.pdf"
  ]
}
```

### Reports

#### Generate Report

```http
GET /api/v2/reports/{report_type}
Authorization: Bearer <token>
```

**Supported Report Types:**
- `applications` - Building consent applications report
- `inspections` - Building inspections report
- `certificates` - Building certificates report
- `fees` - Building fees report
- `compliance` - Building compliance report

**Query Parameters:**
- `date_from` (string): Start date for report
- `date_to` (string): End date for report
- `format` (string): Report format (json, csv, pdf)

### Dashboard Statistics

#### Get Dashboard Stats

```http
GET /api/v2/dashboard/stats
Authorization: Bearer <token>
```

**Response:**
```json
{
  "success": true,
  "data": {
    "applications": {
      "total": 150,
      "draft": 25,
      "submitted": 50,
      "approved": 60,
      "rejected": 15
    },
    "inspections": {
      "total": 200,
      "scheduled": 30,
      "completed": 160,
      "overdue": 10
    },
    "certificates": {
      "total": 60,
      "active": 55,
      "expired": 5
    },
    "fees": {
      "total_revenue": 250000.00,
      "paid_fees": 180,
      "unpaid_fees": 20
    },
    "compliance": {
      "total_requirements": 300,
      "completed": 250,
      "overdue": 15,
      "compliance_rate": 83.33
    }
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

## Error Handling

### HTTP Status Codes

| Status Code | Description |
|-------------|-------------|
| 200 | OK - Request successful |
| 201 | Created - Resource created successfully |
| 400 | Bad Request - Invalid request data |
| 401 | Unauthorized - Authentication required |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource not found |
| 409 | Conflict - Resource conflict |
| 422 | Unprocessable Entity - Validation failed |
| 429 | Too Many Requests - Rate limit exceeded |
| 500 | Internal Server Error - Server error |

### Error Response Format

```json
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": {
    "field": "specific_field_name",
    "message": "Detailed error message"
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Common Error Codes

| Error Code | Description |
|------------|-------------|
| `VALIDATION_ERROR` | Input validation failed |
| `AUTHENTICATION_ERROR` | Authentication failed |
| `AUTHORIZATION_ERROR` | Insufficient permissions |
| `RESOURCE_NOT_FOUND` | Requested resource not found |
| `RESOURCE_CONFLICT` | Resource conflict |
| `RATE_LIMIT_EXCEEDED` | Rate limit exceeded |
| `INTERNAL_ERROR` | Internal server error |

## Response Format

### Success Response

```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### List Response

```json
{
  "success": true,
  "data": [ ... ],
  "count": 25,
  "total": 150,
  "pagination": {
    "page": 1,
    "limit": 25,
    "total_pages": 6,
    "has_next": true,
    "has_prev": false
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

## Pagination

### Parameters

- `limit` (integer): Number of items per page (default: 20, max: 100)
- `offset` (integer): Number of items to skip (default: 0)
- `page` (integer): Page number (alternative to offset)

### Response

```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "page": 2,
    "limit": 20,
    "total": 150,
    "total_pages": 8,
    "has_next": true,
    "has_prev": true,
    "next_url": "/api/v2/building-consents?page=3&limit=20",
    "prev_url": "/api/v2/building-consents?page=1&limit=20"
  }
}
```

## Filtering

### Supported Filters

Most list endpoints support filtering through query parameters:

```http
GET /api/v2/building-consents?status=approved&consent_type=full&date_from=2024-01-01
```

### Filter Operators

- `eq` - Equal to (default)
- `ne` - Not equal to
- `gt` - Greater than
- `gte` - Greater than or equal to
- `lt` - Less than
- `lte` - Less than or equal to
- `like` - Contains (case-insensitive)
- `in` - In array
- `between` - Between two values

### Advanced Filtering

```http
GET /api/v2/building-consents?status=in:approved,submitted&estimated_cost=gt:100000
```

## Sorting

### Parameters

- `sort` (string): Field to sort by
- `order` (string): Sort order (asc, desc) - default: asc

### Examples

```http
GET /api/v2/building-consents?sort=created_at&order=desc
GET /api/v2/building-consents?sort=estimated_cost&order=asc
GET /api/v2/inspections?sort=scheduled_date&order=asc
```

## Webhooks

The API supports webhooks for real-time notifications of important events.

### Supported Events

- `application.submitted` - Application submitted
- `application.approved` - Application approved
- `application.rejected` - Application rejected
- `inspection.scheduled` - Inspection scheduled
- `inspection.completed` - Inspection completed
- `certificate.issued` - Certificate issued
- `fee.paid` - Fee payment received

### Webhook Payload

```json
{
  "event": "application.submitted",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "application_id": "BC2024000001",
    "project_name": "Residential Extension",
    "status": "submitted"
  },
  "webhook_id": "wh_1234567890"
}
```

## SDKs and Libraries

### Official SDKs

- **PHP SDK**: `composer require tpt-gov-platform/php-sdk`
- **JavaScript SDK**: `npm install @tpt-gov-platform/js-sdk`
- **Python SDK**: `pip install tpt-gov-platform`

### Community Libraries

- **Go Client**: Available on GitHub
- **Java Client**: Available on GitHub
- **C# Client**: Available on GitHub

## Changelog

### Version 2.0.0 (Current)

- Enhanced API versioning with backward compatibility
- Improved rate limiting with per-endpoint controls
- Added comprehensive analytics and monitoring
- Introduced webhook system for real-time notifications
- Enhanced error handling and validation
- Added bulk operations support

### Version 1.0.0

- Initial release with core building consent functionality
- Basic CRUD operations for applications, inspections, certificates
- Simple authentication and authorization
- Basic reporting capabilities

## Support

### Documentation

- [API Reference](https://api.tpt-gov-platform.com/docs)
- [Developer Guide](https://developers.tpt-gov-platform.com)
- [SDK Documentation](https://sdk.tpt-gov-platform.com)

### Support Channels

- **Email**: api-support@tpt-gov-platform.com
- **Forum**: https://community.tpt-gov-platform.com
- **GitHub Issues**: https://github.com/tpt-gov-platform/api/issues

### Service Level Agreement

- **Uptime**: 99.9% guaranteed
- **Response Time**: < 200ms for 95% of requests
- **Support Response**: < 4 hours for critical issues

---

*This documentation is automatically generated and updated with each API version release.*
