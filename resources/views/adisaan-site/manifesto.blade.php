@extends('adisaan-site.layout')
@section('title', 'Never Take Teachings Ad Litteram: The AdiSaan Manifesto')
@push('styles')<link rel="stylesheet" href="/assets/adisaan/site/manifesto-paper.css?v=20261031-paper">@endpush
@section('content')
<article class="paper" aria-labelledby="paper-title">
    <header class="paper-cover">
        <div class="paper-shell">
            <div class="paper-cover__top">
                <span>#TBWIM / The Best Way I Imagine</span>
            </div>
            <div class="paper-cover__grid">
                <div class="paper-cover__title">
                    <p class="paper-kicker">A manifesto for human agency in the age of AI</p>
                    <h1 id="paper-title">Never take teachings<br><em>ad litteram</em>.</h1>
                    <p class="paper-cover__dek">No guru, doctrine, or machine can live your life for you. This project exists to help you find your best way.</p>
                </div>
                <div class="paper-cover__margin">
                    <span class="paper-cover__mark" aria-hidden="true">&ldquo;</span>
                    <p>What is the best way <em>I imagine?</em></p>
                    <span class="paper-cover__margin-note">The question behind #TBWIM</span>
                </div>
            </div>
            <div class="paper-cover__bottom">
                <span>A public working manifesto, not a completed study</span>
            </div>
        </div>
    </header>

    <div class="paper-shell paper-layout">
        <nav class="paper-contents" aria-label="On this page">
            <p class="paper-contents__label">On this page</p>
            <ol>
                <li><a href="#abstract"><span>00</span> Abstract</a></li>
                <li><a href="#principle"><span>01</span> The principle</a></li>
                <li><a href="#practice"><span>02</span> The practice</a></li>
                <li><a href="#mirror"><span>03</span> The global mirror</a></li>
                <li><a href="#intelligence"><span>04</span> Human &amp; machine</a></li>
                <li><a href="#method"><span>05</span> Method &amp; consent</a></li>
                <li><a href="#million"><span>06</span> The million</a></li>
            </ol>
        </nav>

        <div class="paper-body">
            <section class="paper-section paper-abstract" id="abstract" aria-labelledby="abstract-title">
                <p class="paper-section__label"><span>00</span> Abstract</p>
                <h2 id="abstract-title">A question before an answer</h2>
                <p class="paper-lead">AdiSaan is an open observatory of human imagination and artificial intelligence. It begins with a simple act: ask a real question, then let people answer from their own lived imagination.</p>
                <p>Its purpose is to make more than one possible way visible. The public work is free to read. A private answer remains private. Machine responses, when available, belong in a separate, clearly labeled record. The hope is that seeing a wider range of thought helps people find an unforced path that fits their actual lives.</p>
                <p><strong>Why this repository exists.</strong> The public web is entering an autophagic loop: models searching an internet filled with synthetic output, distilling poor data into even flatter approximations. As people grow passive, assuming <em>&ldquo;AI knows best; let the machine imagine for me,&rdquo;</em> the original human spark risks atrophy. We built this project to rescue and preserve genuine human imagination before it is displaced by automated consensus.</p>
                <p class="paper-abstract__note"><strong>Central proposition.</strong> Freedom of thought grows when advice becomes something we can examine and test, instead of a script we must obey.</p>
            </section>

            <section class="paper-section" id="principle" aria-labelledby="principle-title">
                <p class="paper-section__label"><span>01</span> The principle</p>
                <h2 id="principle-title">Do not inherit a life by default</h2>
                <p>We are handed instructions about work, success, money, faith, productivity, and happiness. Some are useful. None should be taken <em>ad litteram</em>, or word for word, without testing them against lived reality.</p>
                <p>This is a stand against dogma, including the modern kind delivered by a confident AI prompt. The path of least resistance is not a promise of a life without effort. It is the practice of noticing where effort serves a chosen purpose and where it merely preserves a role that was never ours.</p>
                <blockquote class="paper-pullquote">
                    <p>Machines can calculate.<br>Humans can imagine better.</p>
                    <cite>Our working conviction</cite>
                </blockquote>
            </section>

            <section class="paper-section" id="practice" aria-labelledby="practice-title">
                <p class="paper-section__label"><span>02</span> The practice</p>
                <h2 id="practice-title">A compass you can return to</h2>
                <p>&ldquo;What is the best way I imagine?&rdquo; is a question to use when distraction, fear, or somebody else&rsquo;s certainty pulls you away from an idea. It makes room for a first-person answer: provisional, specific, and yours.</p>
                <ol class="paper-sequence">
                    <li><span>01</span><div><strong>Pause.</strong><p>Name the question without rushing to a borrowed answer.</p></div></li>
                    <li><span>02</span><div><strong>Imagine.</strong><p>Describe the best way you can see from where you stand today.</p></div></li>
                    <li><span>03</span><div><strong>Try.</strong><p>Test one part in life; keep what helps and release what does not.</p></div></li>
                    <li><span>04</span><div><strong>Return.</strong><p>A better answer may arrive after experience changes the question.</p></div></li>
                </ol>
                <p>The question is a tool for reflection. It is not a treatment, a guarantee, or a universal formula.</p>
            </section>

            <section class="paper-section" id="mirror" aria-labelledby="mirror-title">
                <p class="paper-section__label"><span>03</span> The global mirror</p>
                <h2 id="mirror-title">There is more than one way through</h2>
                <p>A problem can feel singular and final when we face it alone. Another person&rsquo;s answer does not solve it for us; it can reveal a possibility we had not considered. A collection of different answers becomes a mirror of human cognitive diversity, not a vote on the one correct life.</p>
                <div class="paper-lenses" aria-label="Four storytelling lenses">
                    <div><span>Night</span><p>The difficult truth we admit in quiet moments.</p></div>
                    <div><span>Dawn</span><p>The possibility visible with a clearer mind.</p></div>
                    <div><span>Midday</span><p>The practical choice tested in the world.</p></div>
                    <div><span>Twilight</span><p>The perspective that comes after doing and resting.</p></div>
                </div>
                <p class="paper-small">These are storytelling lenses, not psychological categories. We do not infer somebody&rsquo;s beliefs, honesty, or state of mind from a clock, a time zone, or a country.</p>
            </section>
            <section class="paper-section" id="intelligence" aria-labelledby="intelligence-title">
                <p class="paper-section__label"><span>04</span> Human &amp; machine</p>
                <h2 id="intelligence-title">Why we build a database of human imagination</h2>
                <p class="paper-lead">The public web is falling into a dangerous loop: machines searching an internet filled with synthetic, diluted text, distilling recycled outputs from already impoverished data. At the same time, humans risk cognitive atrophy by assuming <em>&ldquo;AI knows best; let the machine imagine for me.&rdquo;</em></p>
                <p>This project was built to stop that decline.</p>
                <h3 style="font-size:clamp(18px,1.8vw,22px);color:var(--paper-ink);margin:24px 0 10px;">The problem: the synthetic loop and cognitive surrender</h3>
                <p>When millions surrender their imagination to algorithmic prompts, originality shrinks to zero. Large models trained on web crawls increasingly ingest synthetic debris created by previous models. In machine learning, this is known as model collapse: a mathematical narrowing where nuance, edge cases, and genuine friction disappear.</p>
                <p>If we allow the web to become an echo chamber of machine-generated answers, tomorrow will be nothing more than a copy of a copy of a copy.</p>
                <h3 style="font-size:clamp(18px,1.8vw,22px);color:var(--paper-ink);margin:24px 0 10px;">What this project solves: the sanctuary of original thought</h3>
                <p><strong>#TBWIM (The Best Way I Imagine)</strong> is an intentional counter-movement. We do not prompt a machine to guess what people want. We prompt real people to rekindle their own capacity to imagine.</p>
                <p>Real humans experience fear, joy, sacrifice, practical grit, and unexpected wisdom. That lived biological reality cannot be synthesized from a cold GPU cluster. By asking open, uncompromising questions, we construct a <strong>clean, unpolluted repository of genuine human imagination</strong>.</p>
                <p>The role of artificial intelligence in AdiSaan is not to replace the human mind, but to act as a clear, rigorous mirror: to organize, synthesize, and reflect back to humanity what people actually dare to envision.</p>
                <div class="paper-rule"><span>Human imagination</span><span aria-hidden="true">&ne;</span><span>Synthetic consensus</span></div>
            </section>

            <section class="paper-section" id="method" aria-labelledby="method-title">
                <p class="paper-section__label"><span>05</span> Method &amp; consent</p>
                <h2 id="method-title">Make the process inspectable</h2>
                <p>A credible observatory shows where every conclusion came from. These are the rules we intend to follow as the public record grows.</p>
                <dl class="paper-method">
                    <div><dt><span>01</span> Ask</dt><dd>Publish the question and preserve its wording and date.</dd></div>
                    <div><dt><span>02</span> Receive</dt><dd>Let contributors choose public or private. Do not display private answers.</dd></div>
                    <div><dt><span>03</span> Separate</dt><dd>Label human and AI responses by source. Never count an agent as a human voice.</dd></div>
                    <div><dt><span>04</span> Examine</dt><dd>Report the number of usable answers, how they were collected, and what is missing.</dd></div>
                    <div><dt><span>05</span> Revise</dt><dd>Publish findings with a date and method; correct them when new evidence warrants it.</dd></div>
                </dl>
                <p class="paper-small">For the first synthesis, an eligible answer is a public, human-labeled site submission of at least 40 characters that passes the intake guard. AI answers and social comments do not count toward the five-voice minimum. Social comments require an explicit research-consent path before they can be included.</p>
                <p class="paper-small">People who answer are a self-selected group. Their responses cannot stand for humanity as a whole. Platform comments may be incomplete or inaccessible. The <a href="{{ route('adisaan.public.privacy') }}">privacy page</a> explains the current form and data choices.</p>
            </section>

            <section class="paper-section" id="million" aria-labelledby="million-title">
                <p class="paper-section__label"><span>06</span> The million</p>
                <h2 id="million-title">A signal of attention, not a proof of truth</h2>
                <p>The question about making one million euro catches attention because money is a familiar measure of success. It is an entry point to a deeper question: what would you build or change if you trusted your own imagination?</p>
                <p>One million measured follows is a distribution goal. It would show that a conversation can travel; it would not prove a theory about consciousness or count one million unique people. A future idea to raise money for named projects belongs to a separate stage with its own rules and legal work. <strong>No money is being collected here.</strong></p>
                <p>For the public progress record, <a href="{{ route('adisaan.public.home') }}">follow the million</a>.</p>
            </section>

            <footer class="paper-conclusion" aria-labelledby="conclusion-title">
                <p class="paper-section__label">The invitation</p>
                <h2 id="conclusion-title">Bring an answer only you can give</h2>
                <p>You do not have to agree with this manifesto. Start with today&rsquo;s question, answer in your own words if you wish, and see what another perspective makes possible.</p>
                <a class="paper-action" href="{{ route('adisaan.public.answer', ['story' => $current['id'] ?? null]) }}">Answer today&rsquo;s question <span aria-hidden="true">↗</span></a>
                <p class="paper-conclusion__note">Participation is optional. Public answers are free to read. Your answer can stay private.</p>
            </footer>
        </div>
    </div>
</article>
@endsection
