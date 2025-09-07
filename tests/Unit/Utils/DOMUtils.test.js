/**
 * Unit tests for DOMUtils
 *
 * @package TPT
 * @subpackage Tests
 */

import { DOMUtils } from '../../../public/js/utils.js';

describe('DOMUtils', () => {
    let mockElement;
    let container;

    beforeEach(() => {
        // Set up DOM elements for testing
        container = document.createElement('div');
        container.innerHTML = `
            <div id="test-element" class="test-class" data-test="value">
                <span class="child">Child 1</span>
                <span class="child">Child 2</span>
            </div>
            <button id="test-button">Click me</button>
        `;
        document.body.appendChild(container);

        mockElement = container.querySelector('#test-element');
    });

    afterEach(() => {
        document.body.removeChild(container);
    });

    describe('createElement', () => {
        test('should create element with tag name', () => {
            const element = DOMUtils.createElement('div');
            expect(element.tagName).toBe('DIV');
        });

        test('should create element with attributes', () => {
            const element = DOMUtils.createElement('input', {
                type: 'text',
                id: 'test-input',
                className: 'form-control',
                placeholder: 'Enter text'
            });

            expect(element.type).toBe('text');
            expect(element.id).toBe('test-input');
            expect(element.className).toBe('form-control');
            expect(element.placeholder).toBe('Enter text');
        });

        test('should create element with dataset attributes', () => {
            const element = DOMUtils.createElement('div', {
                dataset: {
                    test: 'value',
                    another: '123'
                }
            });

            expect(element.dataset.test).toBe('value');
            expect(element.dataset.another).toBe('123');
        });

        test('should create element with event listeners', () => {
            const mockHandler = jest.fn();
            const element = DOMUtils.createElement('button', {
                onclick: mockHandler
            });

            element.click();
            expect(mockHandler).toHaveBeenCalled();
        });

        test('should create element with text content', () => {
            const element = DOMUtils.createElement('span', {}, 'Hello World');
            expect(element.textContent).toBe('Hello World');
        });

        test('should create element with HTML content', () => {
            const element = DOMUtils.createElement('div', {}, '<strong>Bold</strong>');
            expect(element.innerHTML).toBe('<strong>Bold</strong>');
        });
    });

    describe('$ and $$', () => {
        test('should select single element with $', () => {
            const element = DOMUtils.$('#test-element');
            expect(element).toBe(mockElement);
        });

        test('should return null for non-existent element with $', () => {
            const element = DOMUtils.$('#non-existent');
            expect(element).toBeNull();
        });

        test('should select multiple elements with $$', () => {
            const elements = DOMUtils.$$('.child');
            expect(elements).toHaveLength(2);
            expect(elements[0].textContent).toBe('Child 1');
            expect(elements[1].textContent).toBe('Child 2');
        });

        test('should return empty array for non-existent elements with $$', () => {
            const elements = DOMUtils.$$('.non-existent');
            expect(elements).toHaveLength(0);
        });
    });

    describe('on (event handling)', () => {
        test('should add direct event listener', () => {
            const mockHandler = jest.fn();
            const button = container.querySelector('#test-button');

            DOMUtils.on(button, 'click', mockHandler);
            button.click();

            expect(mockHandler).toHaveBeenCalledTimes(1);
        });

        test('should add event listener with delegation', () => {
            const mockHandler = jest.fn();
            const child = container.querySelector('.child');

            DOMUtils.on(container, 'click', '.child', mockHandler);
            child.click();

            expect(mockHandler).toHaveBeenCalledTimes(1);
        });

        test('should not trigger delegated event for non-matching elements', () => {
            const mockHandler = jest.fn();

            DOMUtils.on(container, 'click', '.non-existent', mockHandler);
            mockElement.click();

            expect(mockHandler).not.toHaveBeenCalled();
        });
    });

    describe('toggle', () => {
        test('should toggle element visibility', () => {
            const element = document.createElement('div');
            element.style.display = 'block';

            // Hide element
            const result1 = DOMUtils.toggle(element, false);
            expect(element.style.display).toBe('none');
            expect(result1).toBe(false);

            // Show element
            const result2 = DOMUtils.toggle(element, true);
            expect(element.style.display).toBe('');
            expect(result2).toBe(true);
        });

        test('should toggle without parameter', () => {
            const element = document.createElement('div');
            element.style.display = 'block';

            // Should hide
            DOMUtils.toggle(element);
            expect(element.style.display).toBe('none');

            // Should show
            DOMUtils.toggle(element);
            expect(element.style.display).toBe('');
        });
    });

    describe('class manipulation', () => {
        test('should add class', () => {
            DOMUtils.addClass(mockElement, 'new-class');
            expect(mockElement.classList.contains('new-class')).toBe(true);
        });

        test('should remove class', () => {
            mockElement.classList.add('test-class');
            DOMUtils.removeClass(mockElement, 'test-class');
            expect(mockElement.classList.contains('test-class')).toBe(false);
        });

        test('should toggle class', () => {
            // Add class
            DOMUtils.toggleClass(mockElement, 'toggle-class');
            expect(mockElement.classList.contains('toggle-class')).toBe(true);

            // Remove class
            DOMUtils.toggleClass(mockElement, 'toggle-class');
            expect(mockElement.classList.contains('toggle-class')).toBe(false);
        });

        test('should check if element has class', () => {
            expect(DOMUtils.hasClass(mockElement, 'test-class')).toBe(true);
            expect(DOMUtils.hasClass(mockElement, 'non-existent')).toBe(false);
        });
    });
});
