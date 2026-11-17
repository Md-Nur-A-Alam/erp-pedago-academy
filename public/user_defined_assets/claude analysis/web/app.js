(() => {
    'use strict';

    const sections = Array.from(document.querySelectorAll('main section'));
    const tocList = document.getElementById('toc-list');
    const toc = document.getElementById('toc');
    const menu = document.getElementById('menu');
    const links = new Map();

    // ----- contents list -----
    sections.forEach((section) => {
        const item = document.createElement('li');
        const link = document.createElement('a');
        link.href = '#' + section.id;
        link.textContent = section.dataset.title;
        link.addEventListener('click', () => setMenu(false));
        item.appendChild(link);
        tocList.appendChild(item);
        links.set(section.id, link);
    });

    const setMenu = (open) => {
        toc.classList.toggle('open', open);
        menu.setAttribute('aria-expanded', String(open));
    };
    menu.addEventListener('click', () => setMenu(!toc.classList.contains('open')));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setMenu(false);
        }
    });

    // ----- highlight the section being read -----
    const spy = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                links.forEach((link) => link.classList.remove('on'));
                links.get(entry.target.id).classList.add('on');
            }
        });
    }, { rootMargin: '-80px 0px -65% 0px' });
    sections.forEach((section) => spy.observe(section));

    // ----- filter -----
    const search = document.getElementById('search');
    const empty = document.createElement('p');
    empty.className = 'empty';
    empty.textContent = 'No section matches. Try another word.';
    empty.hidden = true;
    document.getElementById('content').appendChild(empty);

    search.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        let shown = 0;
        sections.forEach((section) => {
            const match = term === '' || section.textContent.toLowerCase().includes(term);
            section.hidden = !match;
            links.get(section.id).parentElement.hidden = !match;
            shown += match ? 1 : 0;
        });
        empty.hidden = shown > 0;
    });

    // ----- lead temperature -----
    const HOT_MINUTES = 60;
    const WARM_HOURS = 24;
    const COLD_HOURS = 72;
    const age = document.getElementById('age');
    const ageOut = document.getElementById('age-out');
    const agePill = document.getElementById('age-pill');
    const ageText = document.getElementById('age-text');
    const notes = {
        Hot: ['', '— it goes out in the next cycle and sits at the top of the executive’s list.'],
        Warm: ['amber', '— still fresh. It is distributed right after the hot leads.'],
        Cool: ['blue', '— stale. It waits behind fresh leads, but one slot in four is kept for the longest-waiting lead.'],
        Cold: ['slate', '— it has waited too long. It is still called, through the reserved slot or when fresh leads run out.'],
    };

    // The slider is not linear: the first third covers the first two hours, where the change matters most.
    const minutesAt = (position) => {
        if (position <= 35) {
            return Math.round(position / 35 * 120);
        }
        if (position <= 70) {
            return Math.round(120 + (position - 35) / 35 * (48 * 60 - 120));
        }
        return Math.round(48 * 60 + (position - 70) / 30 * (8 * 24 * 60));
    };
    const describe = (minutes) => {
        if (minutes < 60) {
            return minutes + (minutes === 1 ? ' minute' : ' minutes');
        }
        if (minutes < 48 * 60) {
            const hours = Math.round(minutes / 60);
            return hours + (hours === 1 ? ' hour' : ' hours');
        }
        const days = Math.round(minutes / 1440);
        return days + ' days';
    };
    const renderAge = () => {
        const minutes = minutesAt(Number(age.value));
        const temperature = minutes <= HOT_MINUTES ? 'Hot' : minutes <= WARM_HOURS * 60 ? 'Warm' : minutes <= COLD_HOURS * 60 ? 'Cool' : 'Cold';
        ageOut.textContent = describe(minutes);
        agePill.textContent = temperature;
        agePill.className = 'pill ' + notes[temperature][0];
        ageText.textContent = notes[temperature][1];
    };
    age.addEventListener('input', renderAge);
    renderAge();

    // ----- distribution calculator -----
    const inputs = ['calc-l', 'calc-s', 'calc-n'].map((id) => document.getElementById(id));
    const summary = document.getElementById('calc-summary');
    const plan = document.getElementById('calc-plan');
    const clamp = (input) => Math.min(Number(input.max), Math.max(Number(input.min), Math.floor(Number(input.value) || 0)));

    const renderPlan = () => {
        const [leads, executives, batch] = inputs.map(clamp);
        plan.textContent = '';

        if (executives === 0) {
            summary.textContent = 'Nobody is available, so all ' + leads + ' leads wait in the queue.';
            return;
        }
        if (leads === 0) {
            summary.textContent = 'The queue is empty. Available executives get a batch when new leads arrive.';
            return;
        }

        const base = Math.min(batch, Math.floor(leads / executives));
        const extra = base < batch ? leads % executives : 0;
        const assigned = base * executives + extra;
        const remaining = leads - assigned;

        summary.textContent = base >= batch
            ? 'Full batches: ' + batch + ' each. ' + (remaining > 0 ? remaining + ' leads stay in the queue for the next cycle.' : 'The queue is cleared.')
            : base === 0
                ? 'Fewer leads than executives: one lead each for the ' + extra + ' who have been idle longest.'
                : 'Scarcity: ' + base + ' each' + (extra > 0 ? ', and ' + extra + ' more go one each to the longest idle.' : '.');

        for (let index = 0; index < executives; index++) {
            const count = base + (index < extra ? 1 : 0);
            if (count === 0) {
                continue;
            }
            const cell = document.createElement('div');
            const name = document.createElement('b');
            name.textContent = 'Executive ' + (index + 1);
            const dots = document.createElement('span');
            dots.className = 'dots';
            for (let dot = 0; dot < count; dot++) {
                const mark = document.createElement('i');
                mark.className = dot < base ? 'dot' : 'dot extra';
                dots.appendChild(mark);
            }
            cell.append(name, count + (count === 1 ? ' lead' : ' leads'), dots);
            plan.appendChild(cell);
        }
    };
    inputs.forEach((input) => input.addEventListener('input', renderPlan));
    renderPlan();
})();
