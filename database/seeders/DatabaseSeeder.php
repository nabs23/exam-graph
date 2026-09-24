<?php

namespace Database\Seeders;

use App\Models\AttemptAnswer;
use App\Models\Concept;
use App\Models\ConceptProgress;
use App\Models\LearningObjective;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => 'password',
        ]);

        $program = Program::query()->firstOrCreate(['code' => 'CPALE'], ['name' => 'Certified Public Accountant Licensure Examination', 'description' => 'Local ExamGraph MVP program.']);
        $subject = Subject::query()->firstOrCreate(['program_id' => $program->id, 'code' => 'FAR'], ['name' => 'Financial Accounting and Reporting', 'description' => 'Financial reporting concepts for the local FAR review program.', 'sort_order' => 1]);

        $ppe = SyllabusTopic::query()->firstOrCreate(['subject_id' => $subject->id, 'code' => 'FAR-4.2'], ['title' => 'Property, Plant and Equipment', 'description' => 'Recognition, measurement, depreciation, and derecognition of long-lived tangible assets.', 'sort_order' => 1]);
        $initial = SyllabusTopic::query()->firstOrCreate(['subject_id' => $subject->id, 'code' => 'FAR-4.2.1'], ['parent_id' => $ppe->id, 'title' => 'Initial recognition and measurement', 'description' => 'Determine which costs are included when PPE is first recognized.', 'sort_order' => 1]);
        $subsequent = SyllabusTopic::query()->firstOrCreate(['subject_id' => $subject->id, 'code' => 'FAR-4.2.2'], ['parent_id' => $ppe->id, 'title' => 'Subsequent measurement', 'description' => 'Apply depreciation and impairment concepts after recognition.', 'sort_order' => 2]);
        $disposal = SyllabusTopic::query()->firstOrCreate(['subject_id' => $subject->id, 'code' => 'FAR-4.2.3'], ['parent_id' => $ppe->id, 'title' => 'Derecognition and disclosure', 'description' => 'Account for disposals and communicate PPE information.', 'sort_order' => 3]);

        $concepts = [];
        foreach ([
            ['PPE-C01', $ppe, 'PPE definition and scope', 'Identify assets that meet the PPE definition.', 1],
            ['PPE-C02', $initial, 'Recognition criteria', 'Determine when an item qualifies for recognition as PPE.', 2],
            ['PPE-C03', $initial, 'Initial measurement at cost', 'Determine capitalizable cost at initial recognition.', 3],
            ['PPE-C04', $subsequent, 'Depreciation and useful life', 'Calculate depreciation and explain the effect of useful-life estimates.', 4],
            ['PPE-C05', $subsequent, 'Impairment indicators', 'Identify indicators that PPE may be impaired.', 5],
            ['PPE-C06', $disposal, 'Derecognition on disposal', 'Record the disposal of PPE and determine the resulting gain or loss.', 6],
            ['PPE-C07', $disposal, 'PPE disclosure requirements', 'Select useful disclosures for PPE balances and movements.', 7],
        ] as [$code, $topic, $title, $description, $sortOrder]) {
            $concepts[$code] = Concept::query()->firstOrCreate(
                ['subject_id' => $subject->id, 'code' => $code],
                ['syllabus_topic_id' => $topic->id, 'title' => $title, 'description' => $description, 'sort_order' => $sortOrder],
            );
        }

        $concepts['PPE-C02']->prerequisites()->syncWithoutDetaching([$concepts['PPE-C01']->id]);
        $concepts['PPE-C03']->prerequisites()->syncWithoutDetaching([$concepts['PPE-C02']->id]);
        $concepts['PPE-C04']->prerequisites()->syncWithoutDetaching([$concepts['PPE-C03']->id]);
        $concepts['PPE-C05']->prerequisites()->syncWithoutDetaching([$concepts['PPE-C04']->id]);
        $concepts['PPE-C06']->prerequisites()->syncWithoutDetaching([$concepts['PPE-C03']->id]);
        $concepts['PPE-C07']->prerequisites()->syncWithoutDetaching([$concepts['PPE-C06']->id]);

        foreach ([
            ['PPE-C01', 'What is PPE?', 'Identify tangible long-lived assets and distinguish them from inventory and intangible assets.', <<<'LESSON'
Property, plant and equipment (PPE) are tangible items held for production or supply, rental to others, or administration, and expected to be used during more than one reporting period.

Apply three tests: the item has physical substance, it is held for operational use rather than ordinary-course resale, and it will provide service potential beyond one reporting period. A factory building, production machine, delivery vehicle, and office equipment are common examples.

Land is generally not depreciated because it normally has an unlimited useful life; the building on the land is depreciated separately. A patent is an intangible asset, merchandise held for sale is inventory, and a right to receive cash is a financial asset.

Exam checkpoint: “tangible” alone is not enough. Always consider the asset’s intended use.
LESSON
                , 1],
            ['PPE-C02', 'When is PPE recognized?', 'Apply the two recognition criteria before recording an item as PPE.', <<<'LESSON'
Recognize PPE only when both conditions are satisfied: future economic benefits associated with the item are probable, and the item’s cost can be measured reliably.

Reliable measurement usually comes from a purchase invoice, construction contract, or supportable valuation. Cash payment is not required; a financed acquisition can create PPE and a liability. An order for a machine that has not yet been delivered normally does not create PPE because the entity does not yet control an available resource.

Recognition asks whether an asset should be recorded. Depreciation, impairment, and revaluation are subsequent-measurement questions and should not be confused with recognition.

Exam checkpoint: reject answers based only on payment, management intention, or an expected price increase.
LESSON
                , 1],
            ['PPE-C03', 'Cost at initial recognition', 'Build the initial carrying amount from purchase price and directly attributable costs.', <<<'LESSON'
At initial recognition, PPE is measured at cost. Cost includes purchase price, import duties, non-refundable purchase taxes, and directly attributable costs needed to bring the asset to the location and condition necessary for intended operation.

Directly attributable costs may include site preparation, delivery, handling, installation, assembly, testing, and professional fees. Include an initial estimate of dismantling or restoration costs when the entity has a present obligation.

Trade discounts and rebates reduce cost. Recoverable input taxes, abnormal waste, general administration, employee training, advertising, and start-up losses are normally expensed because they do not prepare the asset for use.

Example: equipment costs ₱1,000,000 less a ₱50,000 discount, plus ₱40,000 delivery and ₱60,000 installation. Initial PPE cost is ₱1,050,000; recoverable VAT and staff training are excluded.
LESSON
                , 1],
            ['PPE-C03', 'Cost examples and exclusions', 'Classify common acquisition and implementation expenditures consistently.', <<<'LESSON'
Capitalize freight, customs duties, site preparation, assembly, installation, and testing when they are directly attributable to bringing the asset to the location and condition necessary for use.

Expense advertising, employee training, relocation, general overhead, abnormal start-up losses, and costs incurred after the asset is capable of operating as intended. Routine repairs and maintenance are also expenses unless a qualifying replacement or major inspection is recognized separately.

The capitalization boundary is the point at which the asset is ready for intended operation. Do not capitalize an entire project invoice automatically just because every charge relates to the same project.

Exam checkpoint: ask whether the expenditure gets the asset ready for use or merely supports the business around it.
LESSON
                , 2],
            ['PPE-C04', 'Depreciation from available-for-use date', 'Calculate systematic depreciation and explain why the available-for-use date matters.', <<<'LESSON'
Depreciation systematically allocates depreciable amount over useful life. Depreciable amount is cost less residual value. It is an allocation process, not a reserve for replacement and not a measure of market value.

Depreciation begins when the asset is available for use: it is in the location and condition necessary to operate as intended. It does not wait until normal production or the first customer order. Depreciation stops on derecognition or classification as held for sale, as applicable.

Straight-line depreciation = (Cost − Residual value) ÷ Useful life. A machine costing ₱1,200,000 with ₱120,000 residual value and a six-year life has annual depreciation of ₱180,000. If available on 1 April, nine months produces ₱135,000 for a 31 December year-end when monthly allocation is used.

Review useful life, residual value, and method at least annually. Changes caused by new information are changes in estimate accounted for prospectively.
LESSON
                , 1],
            ['PPE-C05', 'Impairment indicators', 'Recognize when a PPE carrying amount may no longer be recoverable.', <<<'LESSON'
PPE is impaired when carrying amount exceeds recoverable amount. Recoverable amount is the higher of fair value less costs of disposal and value in use.

External indicators include a significant decline in market value, adverse technological or legal changes, higher market interest rates, or market capitalization below net assets. Internal indicators include physical damage, obsolescence, planned discontinuance, poor performance, or evidence that the asset will be idle.

When an indicator exists, estimate recoverable amount and recognize the excess of carrying amount over that amount. After impairment, calculate future depreciation using the revised carrying amount and remaining useful life.

Example: carrying amount is ₱900,000, fair value less disposal costs is ₱620,000, and value in use is ₱700,000. Recoverable amount is ₱700,000, so impairment is ₱200,000.
LESSON
                , 1],
            ['PPE-C06', 'Disposal accounting', 'Remove disposed PPE and determine the resulting gain or loss.', <<<'LESSON'
Derecognize PPE when it is disposed of or when no future economic benefits are expected from use or disposal. Remove cost and accumulated depreciation, record disposal proceeds, and recognize the difference in profit or loss.

Gain or loss = Net disposal proceeds − Carrying amount at derecognition. Carrying amount is cost less accumulated depreciation and impairment; do not compare proceeds directly with original cost.

Example: a vehicle costs ₱800,000 and has ₱520,000 accumulated depreciation. Its carrying amount is ₱280,000. Sale proceeds of ₱310,000 less ₱10,000 selling costs produce a ₱20,000 gain.

Record depreciation through the appropriate derecognition date, then remove the asset and related accumulated depreciation.
LESSON
                , 1],
            ['PPE-C07', 'PPE disclosure requirements', 'Read the notes needed to understand PPE balances, measurement, and movements.', <<<'LESSON'
PPE disclosures help readers reconcile opening and closing carrying amounts and understand the judgments behind the numbers.

For each class of PPE, notes commonly state the measurement basis, depreciation methods, useful lives or rates, and gross carrying amount and accumulated depreciation or impairment at the beginning and end of the period. A reconciliation shows additions, disposals, depreciation, impairment, revaluations, exchange differences, and other movements.

Additional disclosures may include restrictions on title, assets pledged as security, contractual commitments to acquire PPE, and significant estimates such as useful lives, residual values, and impairment assumptions. Revalued assets require the relevant revaluation date, methods, assumptions, and cost-model comparison when applicable.

Exam checkpoint: a complete PPE note answers what classes exist, how they are measured, how they are depreciated or impaired, and what changed during the period.
LESSON
                , 1],
        ] as [$code, $title, $summary, $content, $sortOrder]) {
            Lesson::query()->updateOrCreate(
                ['concept_id' => $concepts[$code]->id, 'title' => $title],
                ['summary' => $summary, 'content' => $content, 'sort_order' => $sortOrder],
            );
        }

        foreach ([
            ['PPE-C01', 'Classify tangible long-lived assets as PPE or non-PPE.'],
            ['PPE-C01', 'Explain why expected use for more than one period matters.'],
            ['PPE-C02', 'Apply the probability and reliable-measurement recognition criteria.'],
            ['PPE-C03', 'Distinguish capitalizable costs from period expenses.'],
            ['PPE-C03', 'Compute the initial cost of PPE from a list of expenditures.'],
            ['PPE-C04', 'Calculate straight-line depreciation using a residual value.'],
            ['PPE-C05', 'Recognize common internal and external impairment indicators.'],
            ['PPE-C06', 'Compute the gain or loss on disposal.'],
            ['PPE-C07', 'Identify the PPE disclosures needed in the notes.'],
        ] as [$code, $description]) {
            LearningObjective::query()->firstOrCreate(['concept_id' => $concepts[$code]->id, 'description' => $description], ['sort_order' => $concepts[$code]->learningObjectives()->count() + 1]);
        }

        $questionData = [
            ['PPE-C01', 'Which item is most likely within the scope of PPE?', 'A tangible asset held for use over multiple periods is generally PPE.', 'easy', 'Office building held for administrative use', 'A one-month prepaid insurance policy', 'Inventory held for resale', 'A customer deposit'],
            ['PPE-C02', 'Which condition is required before PPE is recognized?', 'Both probable future benefits and reliable measurement are required.', 'easy', 'Future benefits are probable and cost is reliably measurable', 'The asset has already been fully depreciated', 'The asset is paid for in cash only', 'Management expects a price increase'],
            ['PPE-C03', 'Which cost is normally included in the initial cost of PPE?', 'Directly attributable costs needed to bring an asset to working condition are capitalized.', 'medium', 'Routine staff training after the asset is ready for use', 'Delivery and installation needed to make the asset operational', 'Advertising the new asset to customers', 'A loss from initial operating inefficiency'],
            ['PPE-C03', 'Which expenditure is normally expensed rather than capitalized as PPE?', 'Advertising does not bring the asset to the location and condition necessary for use.', 'medium', 'Freight for delivery to the site', 'Site preparation', 'Advertising the new asset to customers', 'Installation testing'],
            ['PPE-C04', 'When does depreciation normally begin?', 'Depreciation starts when the asset is available for use, not necessarily when it is first used.', 'easy', 'When the asset is available for use', 'When the final invoice is paid', 'When the asset is sold', 'When the first repair is performed'],
            ['PPE-C05', 'Which is an impairment indicator?', 'A significant decline in an asset’s market value may indicate impairment.', 'medium', 'A significant decline in market value', 'An increase in useful life estimate', 'A routine monthly electricity bill', 'A favorable exchange-rate movement'],
            ['PPE-C06', 'How is the gain or loss on disposal determined?', 'Compare proceeds with the asset’s carrying amount at derecognition.', 'medium', 'Proceeds less carrying amount', 'Original cost less depreciation expense for the year', 'Proceeds plus accumulated depreciation', 'Fair value less original cost only'],
            ['PPE-C07', 'Which information is commonly disclosed for PPE?', 'Notes commonly explain classes, measurement bases, depreciation, and movements.', 'medium', 'Classes of PPE and accumulated depreciation', 'Only the name of the cashier', 'Only next year’s advertising budget', 'Only the date the entity was incorporated'],
        ];

        $quizzes = [];
        foreach ($questionData as $index => [$code, $prompt, $explanation, $difficulty, $correct, $choiceTwo, $choiceThree, $choiceFour]) {
            $question = Question::query()->firstOrCreate(['concept_id' => $concepts[$code]->id, 'prompt' => $prompt], ['explanation' => $explanation, 'difficulty' => $difficulty, 'sort_order' => $index + 1]);
            $choices = [$correct, $choiceTwo, $choiceThree, $choiceFour];
            foreach ($choices as $choiceIndex => $content) {
                QuestionChoice::query()->firstOrCreate(['question_id' => $question->id, 'content' => $content], ['is_correct' => $choiceIndex === 0, 'sort_order' => $choiceIndex + 1]);
            }

            $quiz = $quizzes[$code] ??= Quiz::query()->firstOrCreate(['concept_id' => $concepts[$code]->id, 'title' => $concepts[$code]->title.' Quiz'], ['description' => 'Check your understanding of '.$concepts[$code]->title.'.', 'passing_score' => 80]);
            $quiz->questions()->syncWithoutDetaching([$question->id => ['sort_order' => $quiz->questions()->count() + 1]]);
        }

        $attempt = QuizAttempt::query()->firstOrCreate(['user_id' => $user->id, 'quiz_id' => $quizzes['PPE-C03']->id], ['started_at' => now()->subDays(2), 'submitted_at' => now()->subDays(2), 'score' => 100, 'correct_answers' => 2, 'total_questions' => 2, 'passed' => true]);
        foreach ($quizzes['PPE-C03']->questions as $question) {
            $choice = $question->choices()->where('is_correct', true)->first();
            AttemptAnswer::query()->firstOrCreate(['quiz_attempt_id' => $attempt->id, 'question_id' => $question->id], ['question_choice_id' => $choice->id, 'is_correct' => true]);
        }
        ConceptProgress::query()->updateOrCreate(['user_id' => $user->id, 'concept_id' => $concepts['PPE-C03']->id], ['last_score' => 100, 'best_score' => 100, 'attempts_count' => 1, 'last_attempted_at' => now()->subDays(2), 'is_completed' => true]);
    }
}
