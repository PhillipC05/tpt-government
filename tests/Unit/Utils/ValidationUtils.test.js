/**
 * Unit tests for ValidationUtils
 *
 * @package TPT
 * @subpackage Tests
 */

import { ValidationUtils } from '../../../public/js/utils.js';

describe('ValidationUtils', () => {
    describe('isValidEmail', () => {
        test('should validate correct email addresses', () => {
            expect(ValidationUtils.isValidEmail('test@example.com')).toBe(true);
            expect(ValidationUtils.isValidEmail('user.name@domain.co.uk')).toBe(true);
            expect(ValidationUtils.isValidEmail('test+tag@gmail.com')).toBe(true);
            expect(ValidationUtils.isValidEmail('123@test.org')).toBe(true);
        });

        test('should reject invalid email addresses', () => {
            expect(ValidationUtils.isValidEmail('')).toBe(false);
            expect(ValidationUtils.isValidEmail('test')).toBe(false);
            expect(ValidationUtils.isValidEmail('test@')).toBe(false);
            expect(ValidationUtils.isValidEmail('@example.com')).toBe(false);
            expect(ValidationUtils.isValidEmail('test.example.com')).toBe(false);
            expect(ValidationUtils.isValidEmail('test@.com')).toBe(false);
            expect(ValidationUtils.isValidEmail('test..test@example.com')).toBe(false);
        });

        test('should handle edge cases', () => {
            expect(ValidationUtils.isValidEmail('a@b.c')).toBe(true);
            expect(ValidationUtils.isValidEmail('test@example')).toBe(false);
            expect(ValidationUtils.isValidEmail('test@example.')).toBe(false);
        });
    });

    describe('isValidPhone', () => {
        test('should validate correct phone numbers', () => {
            expect(ValidationUtils.isValidPhone('+1234567890')).toBe(true);
            expect(ValidationUtils.isValidPhone('1234567890')).toBe(true);
            expect(ValidationUtils.isValidPhone('123 456 7890')).toBe(true);
            expect(ValidationUtils.isValidPhone('(123) 456-7890')).toBe(true);
            expect(ValidationUtils.isValidPhone('+44 20 7123 4567')).toBe(true);
        });

        test('should reject invalid phone numbers', () => {
            expect(ValidationUtils.isValidPhone('')).toBe(false);
            expect(ValidationUtils.isValidPhone('abc')).toBe(false);
            expect(ValidationUtils.isValidPhone('123')).toBe(false);
            expect(ValidationUtils.isValidPhone('+')).toBe(false);
            expect(ValidationUtils.isValidPhone('0123456789')).toBe(false); // starts with 0
        });

        test('should handle various formats', () => {
            expect(ValidationUtils.isValidPhone('555-123-4567')).toBe(true);
            expect(ValidationUtils.isValidPhone('555.123.4567')).toBe(true);
            expect(ValidationUtils.isValidPhone('+1 (555) 123-4567')).toBe(true);
        });
    });

    describe('isValidPostalCode', () => {
        test('should validate US postal codes', () => {
            expect(ValidationUtils.isValidPostalCode('12345', 'US')).toBe(true);
            expect(ValidationUtils.isValidPostalCode('12345-6789', 'US')).toBe(true);
            expect(ValidationUtils.isValidPostalCode('123456', 'US')).toBe(false);
            expect(ValidationUtils.isValidPostalCode('1234', 'US')).toBe(false);
            expect(ValidationUtils.isValidPostalCode('abcde', 'US')).toBe(false);
        });

        test('should validate Canadian postal codes', () => {
            expect(ValidationUtils.isValidPostalCode('K1A 0A6', 'CA')).toBe(true);
            expect(ValidationUtils.isValidPostalCode('K1A0A6', 'CA')).toBe(true);
            expect(ValidationUtils.isValidPostalCode('k1a 0a6', 'CA')).toBe(true);
            expect(ValidationUtils.isValidPostalCode('123456', 'CA')).toBe(false);
            expect(ValidationUtils.isValidPostalCode('K1A0A', 'CA')).toBe(false);
        });

        test('should validate UK postal codes', () => {
            expect(ValidationUtils.isValidPostalCode('SW1A 1AA', 'UK')).toBe(true);
            expect(ValidationUtils.isValidPostalCode('SW1A1AA', 'UK')).toBe(true);
            expect(ValidationUtils.isValidPostalCode('M1 1AE', 'UK')).toBe(true);
            expect(ValidationUtils.isValidPostalCode('12345', 'UK')).toBe(false);
        });

        test('should return true for unsupported countries', () => {
            expect(ValidationUtils.isValidPostalCode('any', 'XX')).toBe(true);
            expect(ValidationUtils.isValidPostalCode('', 'XX')).toBe(true);
        });
    });

    describe('sanitizeString', () => {
        test('should remove HTML tags', () => {
            expect(ValidationUtils.sanitizeString('<script>alert("xss")</script>')).toBe('scriptalert("xss")/script');
            expect(ValidationUtils.sanitizeString('<b>Bold</b>')).toBe('bBold/b');
            expect(ValidationUtils.sanitizeString('<a href="#">Link</a>')).toBe('a href="#"Link/a');
        });

        test('should trim whitespace', () => {
            expect(ValidationUtils.sanitizeString('  test  ')).toBe('test');
            expect(ValidationUtils.sanitizeString('\t\ntest\t\n')).toBe('test');
        });

        test('should handle empty strings', () => {
            expect(ValidationUtils.sanitizeString('')).toBe('');
            expect(ValidationUtils.sanitizeString('   ')).toBe('');
        });

        test('should handle normal strings', () => {
            expect(ValidationUtils.sanitizeString('Hello World')).toBe('Hello World');
            expect(ValidationUtils.sanitizeString('Test 123')).toBe('Test 123');
        });
    });

    describe('checkPasswordStrength', () => {
        test('should return weak password for short passwords', () => {
            const result = ValidationUtils.checkPasswordStrength('123');
            expect(result.score).toBe(1);
            expect(result.strength).toBe('weak');
            expect(result.checks.length).toBe(1);
        });

        test('should return medium password for moderate strength', () => {
            const result = ValidationUtils.checkPasswordStrength('Password1');
            expect(result.score).toBe(4);
            expect(result.strength).toBe('medium');
            expect(result.checks.length).toBe(true);
            expect(result.checks.uppercase).toBe(true);
            expect(result.checks.lowercase).toBe(true);
            expect(result.checks.number).toBe(true);
        });

        test('should return strong password for high strength', () => {
            const result = ValidationUtils.checkPasswordStrength('MyStr0ng!P@ssw0rd');
            expect(result.score).toBe(5);
            expect(result.strength).toBe('strong');
            expect(result.checks.length).toBe(true);
            expect(result.checks.uppercase).toBe(true);
            expect(result.checks.lowercase).toBe(true);
            expect(result.checks.number).toBe(true);
            expect(result.checks.special).toBe(true);
        });

        test('should check all password requirements', () => {
            const weakPassword = ValidationUtils.checkPasswordStrength('weak');
            expect(weakPassword.checks.length).toBe(false);
            expect(weakPassword.checks.uppercase).toBe(false);
            expect(weakPassword.checks.lowercase).toBe(true);
            expect(weakPassword.checks.number).toBe(false);
            expect(weakPassword.checks.special).toBe(false);

            const strongPassword = ValidationUtils.checkPasswordStrength('Str0ng!Pass');
            expect(strongPassword.checks.length).toBe(true);
            expect(strongPassword.checks.uppercase).toBe(true);
            expect(strongPassword.checks.lowercase).toBe(true);
            expect(strongPassword.checks.number).toBe(true);
            expect(strongPassword.checks.special).toBe(true);
        });

        test('should handle empty password', () => {
            const result = ValidationUtils.checkPasswordStrength('');
            expect(result.score).toBe(0);
            expect(result.strength).toBe('weak');
            expect(result.checks.length).toBe(false);
        });

        test('should handle password with only special characters', () => {
            const result = ValidationUtils.checkPasswordStrength('!@#$%^&*()');
            expect(result.score).toBe(2);
            expect(result.checks.length).toBe(true);
            expect(result.checks.special).toBe(true);
        });
    });
});
