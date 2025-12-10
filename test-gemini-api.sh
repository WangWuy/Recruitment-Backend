#!/bin/bash

# Test script for Gemini API endpoints
# Usage: ./test-gemini-api.sh

BASE_URL="http://localhost:9090"

echo "=================================="
echo "Testing Gemini API Endpoints"
echo "=================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Chat endpoint
echo -e "${YELLOW}Test 1: Chat Endpoint${NC}"
echo "POST $BASE_URL/api/gemini/chat"
echo ""

RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/gemini/chat" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Xin chào, bạn có thể giúp tôi tìm việc làm developer không?"
  }')

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" -eq 200 ]; then
  echo -e "${GREEN}✓ Status: $HTTP_CODE${NC}"
  echo "Response:"
  echo "$BODY" | jq '.'
else
  echo -e "${RED}✗ Status: $HTTP_CODE${NC}"
  echo "Response:"
  echo "$BODY" | jq '.'
fi

echo ""
echo "=================================="
echo ""

# Test 2: Job Recommendations
echo -e "${YELLOW}Test 2: Job Recommendations${NC}"
echo "POST $BASE_URL/api/gemini/job-recommendations"
echo ""

RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/gemini/job-recommendations" \
  -H "Content-Type: application/json" \
  -d '{
    "userProfile": {
      "name": "Nguyen Van A",
      "skills": "PHP, Flutter, React",
      "experience": "3 years"
    },
    "jobs": [
      {
        "title": "Senior Flutter Developer",
        "company_name": "Tech Company",
        "location": "Ho Chi Minh",
        "salary_min": "20000000",
        "salary_max": "30000000",
        "description": "Develop mobile apps with Flutter"
      }
    ]
  }')

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" -eq 200 ]; then
  echo -e "${GREEN}✓ Status: $HTTP_CODE${NC}"
  echo "Response:"
  echo "$BODY" | jq '.'
else
  echo -e "${RED}✗ Status: $HTTP_CODE${NC}"
  echo "Response:"
  echo "$BODY" | jq '.'
fi

echo ""
echo "=================================="
echo ""

# Test 3: CV Suggestions
echo -e "${YELLOW}Test 3: CV Suggestions${NC}"
echo "POST $BASE_URL/api/gemini/cv-suggestions"
echo ""

RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/gemini/cv-suggestions" \
  -H "Content-Type: application/json" \
  -d '{
    "cvData": {
      "fullName": "Nguyen Van A",
      "email": "user@example.com",
      "phone": "0123456789",
      "summary": "Experienced developer with 3 years",
      "skills": ["PHP", "Flutter", "React"]
    }
  }')

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" -eq 200 ]; then
  echo -e "${GREEN}✓ Status: $HTTP_CODE${NC}"
  echo "Response:"
  echo "$BODY" | jq '.'
else
  echo -e "${RED}✗ Status: $HTTP_CODE${NC}"
  echo "Response:"
  echo "$BODY" | jq '.'
fi

echo ""
echo "=================================="
echo ""

# Test 4: Interview Prep
echo -e "${YELLOW}Test 4: Interview Preparation${NC}"
echo "POST $BASE_URL/api/gemini/interview-prep"
echo ""

RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/gemini/interview-prep" \
  -H "Content-Type: application/json" \
  -d '{
    "job": {
      "title": "Senior Flutter Developer",
      "company_name": "Tech Company",
      "description": "Develop mobile applications",
      "requirements": "3+ years Flutter experience"
    }
  }')

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" -eq 200 ]; then
  echo -e "${GREEN}✓ Status: $HTTP_CODE${NC}"
  echo "Response:"
  echo "$BODY" | jq '.'
else
  echo -e "${RED}✗ Status: $HTTP_CODE${NC}"
  echo "Response:"
  echo "$BODY" | jq '.'
fi

echo ""
echo "=================================="
echo "Testing Complete"
echo "=================================="
