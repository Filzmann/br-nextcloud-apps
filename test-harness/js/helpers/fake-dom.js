class FakeClassList {
    constructor(initialValues = []) {
        this.values = new Set(initialValues);
    }

    add(name) {
        this.values.add(name);
    }

    remove(name) {
        this.values.delete(name);
    }

    toggle(name, enabled) {
        if (enabled) {
            this.add(name);
        } else {
            this.remove(name);
        }
    }

    contains(name) {
        return this.values.has(name);
    }

    has(name) {
        return this.contains(name);
    }

    toString() {
        return Array.from(this.values).join(' ');
    }
}

function datasetKeyToAttributeName(key) {
    return `data-${key.replace(/[A-Z]/g, (char) => `-${char.toLowerCase()}`)}`;
}

function attributeNameToDatasetKey(name) {
    return name.slice(5).replace(/-([a-z])/g, (_, char) => char.toUpperCase());
}

class FakeElement {
    constructor(idOrAttributes = '') {
        const attributes = typeof idOrAttributes === 'object' && idOrAttributes !== null
            ? idOrAttributes
            : {};

        this.id = typeof idOrAttributes === 'string' ? idOrAttributes : '';
        this.value = '';
        this.checked = false;
        this.hidden = false;
        this.disabled = false;
        this.innerHTML = '';
        this.textContent = '';
        this.className = '';
        this.scrollTop = 0;
        this.dataset = {};
        this.listeners = {};
        this.attributes = {};
        this.classList = new FakeClassList();
        this.focused = false;
        this.selected = false;
        this.queryResults = new Map();
        this.queryAllResults = new Map();

        for (const [name, value] of Object.entries(attributes)) {
            this.setAttribute(name, value);
        }
    }

    addEventListener(type, listener) {
        this.listeners[type] = listener;
    }

    setAttribute(name, value) {
        const stringValue = String(value);
        this.attributes[name] = stringValue;

        if (name.startsWith('data-')) {
            this.dataset[attributeNameToDatasetKey(name)] = stringValue;
        }
    }

    getAttribute(name) {
        return Object.prototype.hasOwnProperty.call(this.attributes, name)
            ? this.attributes[name]
            : null;
    }

    focus() {
        this.focused = true;
    }

    select() {
        this.selected = true;
    }

    closest() {
        return null;
    }

    matches(selector) {
        const presenceMatch = selector.match(/^\[([a-zA-Z0-9-]+)\]$/);

        if (presenceMatch) {
            return this.getAttribute(presenceMatch[1]) !== null;
        }

        return false;
    }

    querySelector(selector) {
        return this.queryResults.get(selector) || null;
    }

    querySelectorAll(selector) {
        return this.queryAllResults.get(selector) || [];
    }
}

class FakeButton extends FakeElement {
    constructor(dataset = {}, id = '') {
        super(id);

        for (const [key, value] of Object.entries(dataset)) {
            this.setAttribute(datasetKeyToAttributeName(key), value);
        }
    }

    closest(selector) {
        if (selector === 'button[data-action]' && this.dataset.action) {
            return this;
        }

        const exactActionMatch = selector.match(/^button\[data-action="([^"]+)"\]$/);
        if (exactActionMatch && this.dataset.action === exactActionMatch[1]) {
            return this;
        }

        if (selector === 'button[data-view]' && this.dataset.view) {
            return this;
        }

        return super.closest(selector);
    }
}

function createElementMap(ids) {
    return new Map(ids.map(id => [id, new FakeElement(id)]));
}

function installDocument(elements, options = {}) {
    const listeners = {};
    const documentElement = options.documentElement || new FakeElement('document-element');
    const body = options.body || new FakeElement('body');

    global.document = {
        documentElement,
        body,
        listeners,
        getElementById(id) {
            return elements.get(id) || null;
        },
        querySelector(selector) {
            if (options.querySelector) {
                return options.querySelector(selector);
            }

            return null;
        },
        querySelectorAll(selector) {
            if (options.querySelectorAll) {
                return options.querySelectorAll(selector);
            }

            return [];
        },
        addEventListener(type, listener) {
            listeners[type] = listener;
        },
    };

    return global.document;
}

module.exports = {
    FakeButton,
    FakeClassList,
    FakeElement,
    createElementMap,
    installDocument,
};
