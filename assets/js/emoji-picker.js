/**
 * Simple Emoji Picker Component
 * Provides emoji selection for chat interfaces
 */

class EmojiPicker {
    constructor() {
        this.emojis = [
            // Smileys & People
            '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇',
            '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '😋', '😛', '😜', '🤪', '😝', '🤑',
            '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬',
            '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵',
            '🥶', '🥴', '😵', '🤯', '🤠', '🥳', '😎', '🤓', '🧐', '😕', '😟', '🙁', '😮',
            '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖',
            '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '😈', '👿', '💀',
            '💋', '💘', '💝', '💖', '💗', '💓', '💞', '💕', '💟', '❣️', '💔', '❤️', '🧡',
            '💛', '💚', '💙', '💜', '🤎', '🖤', '🤍', '💯', '💢', '💥', '💫', '💦', '💨',
            '🕳️', '💣', '💬', '👁️', '🗨️', '🗯️', '💭', '💤',

            // Hand gestures
            '👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈',
            '👉', '👆', '🖕', '👇', '☝️', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌',
            '👐', '🤲', '🤝', '🙏', '💅', '🤳', '💪', '🦵', '🦶',

            // Nature & Animals
            '🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷',
            '🐸', '🐵', '🙈', '🙉', '🙊', '🐒', '🐔', '🐧', '🐦', '🐤', '🐣', '🐥', '🦆',
            '🦅', '🦉', '🦇', '🐺', '🐗', '🐴', '🦄', '🐝', '🐛', '🦋', '🐌', '🐞', '🐜',
            '🦟', '🦗', '🕷️', '🦂', '🐢', '🐍', '🦎', '🦖', '🦕', '🐙', '🦑', '🦐', '🦞',
            '🦀', '🐡', '🐠', '🐟', '🐬', '🐳', '🐋', '🦈', '🐊', '🐅', '🐆', '🦓', '🦍',
            '🦧', '🐘', '🦛', '🦏', '🐪', '🐫', '🦒', '🦘', '🐃', '🐂', '🐄', '🐎', '🐖',
            '🐏', '🐑', '🦙', '🐐', '🦌', '🐕', '🐩', '🦮', '🐈', '🐓', '🦃', '🦚', '🦜',
            '🦢', '🦩', '🕊️', '🐇', '🦝', '🦨', '🦡', '🦦', '🦥', '🐁', '🐀', '🐿️',

            // Food & Drink
            '🍎', '🍏', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🍈', '🍒', '🍑', '🥭', '🍍',
            '🥥', '🥝', '🍅', '🍆', '🥑', '🥦', '🥬', '🥒', '🌶️', '🌽', '🥕', '🧄', '🧅',
            '🥔', '🍠', '🥐', '🥯', '🍞', '🥖', '🥨', '🧀', '🥚', '🍳', '🧈', '🥞', '🧇',
            '🥓', '🥩', '🍗', '🍖', '🦴', '🌭', '🍔', '🍟', '🍕', '🥪', '🥙', '🧆', '🌮',
            '🌯', '🥗', '🥘', '🥫', '🍝', '🍜', '🍲', '🍛', '🍣', '🍱', '🥟', '🦪', '🍤',
            '🍙', '🍚', '🍘', '🍥', '🥠', '🥮', '🍢', '🍡', '🍧', '🍨', '🍦', '🥧', '🧁',
            '🍰', '🎂', '🍮', '🍭', '🍬', '🍫', '🍿', '🍩', '🍪', '🌰', '🥜', '🍯', '🥛',
            '🍼', '☕', '🍵', '🧃', '🥤', '🍶', '🍺', '🍻', '🥂', '🍷', '🥃', '🍸', '🍹',
            '🧉', '🍾', '🧊',

            // Activities & Objects
            '⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🏓', '🏸', '🏒',
            '🏑', '🥍', '🏏', '🥅', '⛳', '🏹', '🎣', '🤿', '🥊', '🥋', '🎽', '🛹', '🛷',
            '⛸️', '🥌', '🎿', '⛷️', '🏂', '🪂', '🏋️', '🤼', '🤸', '🤺', '⛹️', '🤾', '🏌️',
            '🏇', '🧘', '🏊', '🤽', '🚣', '🧗', '🚴', '🚵', '🎪', '🎭', '🎨', '🎬', '🎤',
            '🎧', '🎼', '🎹', '🥁', '🎷', '🎺', '🎸', '🪕', '🎻', '🎲', '♟️', '🎯', '🎳',
            '🎮', '🎰', '🧩',

            // Travel & Places
            '🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐', '🚚', '🚛', '🚜',
            '🛴', '🚲', '🛵', '🏍️', '🛺', '🚨', '🚔', '🚍', '🚘', '🚖', '🚡', '🚠', '🚟',
            '🚃', '🚋', '🚞', '🚝', '🚄', '🚅', '🚈', '🚂', '🚆', '🚇', '🚊', '🚉', '✈️',
            '🛫', '🛬', '🛩️', '💺', '🛰️', '🚁', '🛸', '🚀', '🛶', '⛵', '🚤', '🛥️', '🛳️',
            '⛴️', '🚢', '⚓', '⛽', '🚧', '🚦', '🚥', '🗺️', '🗿', '🗽', '🗼', '🏰', '🏯',
            '🏟️', '🎡', '🎢', '🎠', '⛲', '⛱️', '🏖️', '🏝️', '🏜️', '🌋', '⛰️', '🏔️', '🗻',
            '🏕️', '⛺', '🏠', '🏡', '🏘️', '🏚️', '🏗️', '🏭', '🏢', '🏬', '🏣', '🏤', '🏥',
            '🏦', '🏨', '🏪', '🏫', '🏩', '💒', '🏛️', '⛪', '🕌', '🕍', '🛕',

            // Symbols
            '💯', '🔞', '📵', '🚭', '🚱', '🚳', '🚷', '🚸', '⛔', '📛', '🚫', '💮', '🉐',
            '㊙️', '㊗️', '🈴', '🈵', '🈹', '🈲', '🅰️', '🅱️', '🆎', '🆑', '🅾️', '🆘', '❌',
            '⭕', '🛑', '⛔', '📛', '🚫', '💯', '💢', '♨️', '🚷', '🚯', '🚳', '🚱', '🔞',
            '📵', '🚭', '❗', '❕', '❓', '❔', '‼️', '⁉️', '🔅', '🔆', '〽️', '⚠️', '🚸',
            '🔱', '⚜️', '🔰', '♻️', '✅', '🈯', '💹', '❇️', '✳️', '❎', '🌐', '💠', '➿',
            '♾️', '✔️', '☑️', '🔘', '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '⚫', '⚪', '🟤',
            '🔺', '🔻', '🔸', '🔹', '🔶', '🔷', '🔳', '🔲', '▪️', '▫️', '◾', '◽', '◼️',
            '◻️', '🟥', '🟧', '🟨', '🟩', '🟦', '🟪', '⬛', '⬜', '🟫', '💰', '💎', '💍',
            '👑', '🎁', '🎀', '🎊', '🎉', '🎈', '🏆', '🥇', '🥈', '🥉', '🏅', '🎖️', '🎗️',
            '🏵️', '🌟', '⭐', '🌠', '✨', '💫', '🔥', '💥', '💢', '💦', '💨'
        ];

        this.categories = {
            'smileys': { icon: '😀', label: 'Smileys', start: 0, end: 100 },
            'people': { icon: '👋', label: 'People', start: 100, end: 132 },
            'animals': { icon: '🐶', label: 'Animals', start: 132, end: 220 },
            'food': { icon: '🍎', label: 'Food', start: 220, end: 310 },
            'activities': { icon: '⚽', label: 'Activities', start: 310, end: 370 },
            'travel': { icon: '🚗', label: 'Travel', start: 370, end: 460 },
            'symbols': { icon: '💯', label: 'Symbols', start: 460, end: this.emojis.length }
        };

        this.pickerElement = null;
        this.targetInput = null;
        this.isVisible = false;
    }

    init(buttonSelector, inputSelector) {
        const button = document.querySelector(buttonSelector);
        const input = document.querySelector(inputSelector);

        if (!button || !input) {
            console.warn('EmojiPicker: Button or input not found');
            return;
        }

        this.targetInput = input;

        // Create picker element
        this.createPicker();

        // Button click handler
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggle();
        });

        // Close picker when clicking outside
        document.addEventListener('click', (e) => {
            if (this.isVisible &&
                !this.pickerElement.contains(e.target) &&
                !button.contains(e.target)) {
                this.hide();
            }
        });
    }

    createPicker() {
        this.pickerElement = document.createElement('div');
        this.pickerElement.className = 'emoji-picker';
        this.pickerElement.style.display = 'none';

        // Category tabs
        const tabs = document.createElement('div');
        tabs.className = 'emoji-tabs';

        Object.entries(this.categories).forEach(([key, cat]) => {
            const tab = document.createElement('button');
            tab.className = 'emoji-tab';
            tab.textContent = cat.icon;
            tab.title = cat.label;
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.scrollToCategory(key);
            });
            tabs.appendChild(tab);
        });

        this.pickerElement.appendChild(tabs);

        // Emoji grid
        const grid = document.createElement('div');
        grid.className = 'emoji-grid';

        this.emojis.forEach(emoji => {
            const button = document.createElement('button');
            button.className = 'emoji-button';
            button.textContent = emoji;
            button.title = emoji;
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.insertEmoji(emoji);
            });
            grid.appendChild(button);
        });

        this.pickerElement.appendChild(grid);

        // Add styles
        this.addStyles();

        document.body.appendChild(this.pickerElement);
    }

    addStyles() {
        if (document.getElementById('emoji-picker-styles')) return;

        const style = document.createElement('style');
        style.id = 'emoji-picker-styles';
        style.textContent = `
            .emoji-picker {
                position: absolute;
                background: rgba(0, 0, 0, 0.95);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 15px;
                padding: 0.5rem;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
                z-index: 10000;
                width: 350px;
                max-height: 400px;
                display: flex;
                flex-direction: column;
                backdrop-filter: blur(10px);
            }

            .emoji-tabs {
                display: flex;
                gap: 0.5rem;
                padding: 0.5rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                overflow-x: auto;
            }

            .emoji-tab {
                background: transparent;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 0.25rem 0.5rem;
                border-radius: 8px;
                transition: background 0.2s;
            }

            .emoji-tab:hover {
                background: rgba(255, 255, 255, 0.1);
            }

            .emoji-grid {
                display: grid;
                grid-template-columns: repeat(8, 1fr);
                gap: 0.25rem;
                padding: 0.5rem;
                overflow-y: auto;
                max-height: 300px;
            }

            .emoji-button {
                background: transparent;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 0.5rem;
                border-radius: 8px;
                transition: all 0.2s;
            }

            .emoji-button:hover {
                background: rgba(255, 255, 255, 0.1);
                transform: scale(1.2);
            }

            @media (max-width: 768px) {
                .emoji-picker {
                    width: 300px;
                    max-height: 350px;
                }

                .emoji-grid {
                    grid-template-columns: repeat(6, 1fr);
                }
            }
        `;

        document.head.appendChild(style);
    }

    toggle() {
        if (this.isVisible) {
            this.hide();
        } else {
            this.show();
        }
    }

    show() {
        // Position near the input
        const inputRect = this.targetInput.getBoundingClientRect();
        this.pickerElement.style.display = 'flex';
        this.pickerElement.style.bottom = (window.innerHeight - inputRect.top + 10) + 'px';
        this.pickerElement.style.left = inputRect.left + 'px';
        this.isVisible = true;
    }

    hide() {
        this.pickerElement.style.display = 'none';
        this.isVisible = false;
    }

    insertEmoji(emoji) {
        const start = this.targetInput.selectionStart;
        const end = this.targetInput.selectionEnd;
        const text = this.targetInput.value;

        this.targetInput.value = text.substring(0, start) + emoji + text.substring(end);
        this.targetInput.selectionStart = this.targetInput.selectionEnd = start + emoji.length;
        this.targetInput.focus();

        // Trigger input event for any listeners
        this.targetInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    scrollToCategory(category) {
        const cat = this.categories[category];
        if (!cat) return;

        const grid = this.pickerElement.querySelector('.emoji-grid');
        const buttons = grid.querySelectorAll('.emoji-button');
        const targetButton = buttons[cat.start];

        if (targetButton) {
            targetButton.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

// Export for use in other scripts
window.EmojiPicker = EmojiPicker;
