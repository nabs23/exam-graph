<?php

namespace Database\Seeders;

use App\Models\Concept;
use App\Models\LearningObjective;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use Illuminate\Database\Seeder;

class FarCompleteSeeder extends Seeder
{
    public function run(): void
    {
        $subject = Subject::query()->where('code', 'FAR')->firstOrFail();
        $topics = [];

        foreach ([
            ['FAR-4.2.3', 'FAR-4.2', 'Borrowing costs'],
            ['FAR-4.2.4', 'FAR-4.2', 'Subsequent expenditures'],
            ['FAR-4.2.5', 'FAR-4.2', 'Subsequent measurement'],
            ['FAR-4.2.5.1', 'FAR-4.2.5', 'Cost method'],
            ['FAR-4.2.5.1.1', 'FAR-4.2.5.1', 'Depreciation'],
            ['FAR-4.2.5.1.2', 'FAR-4.2.5.1', 'Depreciation methods'],
            ['FAR-4.2.5.1.3', 'FAR-4.2.5.1', 'Changes in useful life and depreciation methods'],
            ['FAR-4.2.5.2', 'FAR-4.2.5', 'Revaluation'],
            ['FAR-4.2.6', 'FAR-4.2', 'Impairment'],
            ['FAR-4.2.7', 'FAR-4.2', 'Retirement and disposals'],
        ] as $sortOrder => [$code, $parentCode, $title]) {
            $parent = $topics[$parentCode] ?? SyllabusTopic::query()->where('subject_id', $subject->id)->where('code', $parentCode)->firstOrFail();
            $topics[$code] = SyllabusTopic::query()->updateOrCreate(['subject_id' => $subject->id, 'code' => $code], ['parent_id' => $parent->id, 'title' => $title, 'description' => $title, 'sort_order' => $sortOrder + 1]);
        }

        foreach ([
            ['PPE-C06', 'FAR-4.2.3', 'Borrowing-cost capitalization'], ['PPE-C07', 'FAR-4.2.4', 'Component accounting'], ['PPE-C08', 'FAR-4.2.4', 'Subsequent expenditure: capitalize or expense'], ['PPE-C09', 'FAR-4.2.5.1', 'Cost model'], ['PPE-C10', 'FAR-4.2.5.1.1', 'Depreciable amount'], ['PPE-C11', 'FAR-4.2.5.1.1', 'Residual value'], ['PPE-C12', 'FAR-4.2.5.1.1', 'Useful life'], ['PPE-C13', 'FAR-4.2.5.1.1', 'Depreciation commencement and cessation'], ['PPE-C14', 'FAR-4.2.5.1.2', 'Straight-line method'], ['PPE-C15', 'FAR-4.2.5.1.2', 'Diminishing-balance method'], ['PPE-C16', 'FAR-4.2.5.1.2', 'Units-of-production method'], ['PPE-C17', 'FAR-4.2.5.1.3', 'Review of useful life and residual value'], ['PPE-C18', 'FAR-4.2.5.1.3', 'Change in accounting estimate'], ['PPE-C19', 'FAR-4.2.5.2', 'Revaluation model'], ['PPE-C20', 'FAR-4.2.5.2', 'Revaluation surplus'], ['PPE-C21', 'FAR-4.2.5.2', 'Revaluation decrease'], ['PPE-C22', 'FAR-4.2.6', 'Impairment indicators'], ['PPE-C23', 'FAR-4.2.6', 'Recoverable amount'], ['PPE-C24', 'FAR-4.2.6', 'Impairment loss'], ['PPE-C25', 'FAR-4.2.7', 'Derecognition'], ['PPE-C26', 'FAR-4.2.7', 'Gain or loss on disposal'],
        ] as $sortOrder => [$code, $topicCode, $title]) {
            Concept::query()->updateOrCreate(['subject_id' => $subject->id, 'code' => $code], ['syllabus_topic_id' => $topics[$topicCode]->id, 'title' => $title, 'description' => $title, 'sort_order' => $sortOrder + 8]);
        }

        $lessons = [
            ['PPE-C06', 'PPE-L03 — Borrowing Costs', 'Borrowing costs directly attributable to acquiring, constructing, or producing a qualifying asset form part of that asset’s cost. A qualifying asset takes a substantial period to get ready for intended use or sale; ordinary short-term acquisitions do not qualify.'],
            ['PPE-C07', 'PPE-L04 — Subsequent Expenditure and Components', 'Significant components with different useful lives are accounted for separately and depreciated over their own useful lives. Capitalize qualifying replacements and major inspections, then derecognize the carrying amount of the part replaced when it can be identified. Expense routine repairs, maintenance, and servicing because they do not create a separately controlled future benefit.'],
            ['PPE-C08', 'Subsequent expenditure: capitalize or expense', 'After PPE is available for use, capitalize later spending when it is probable that additional future economic benefits will flow to the entity and the cost can be measured reliably. Examples include a qualifying replacement, major inspection, or upgrade that increases capacity, efficiency, useful life, or output quality. Expense routine repairs, cleaning, servicing, maintenance, training, and advertising. When a replacement is capitalized, derecognize the carrying amount of the part replaced if it can be identified. Exam checkpoint: ask whether the cost creates new or improved service potential, not whether the invoice is large.'],
            ['PPE-C09', 'The cost model', 'Under the cost model, PPE is carried after recognition at cost less accumulated depreciation and accumulated impairment losses. Cost remains the starting point, but the carrying amount changes for depreciation, qualifying replacement or inspection components, derecognition, and impairment. Example: cost of ₱1,000,000 less accumulated depreciation of ₱300,000 and impairment of ₱50,000 gives a carrying amount of ₱650,000. Do not substitute market value unless the revaluation model is applied.'],
            ['PPE-C10', 'PPE-L05 — Depreciation Foundations', 'Depreciation is the systematic allocation of depreciable amount over useful life. Depreciable amount is cost less residual value. Depreciation begins when the asset is available for use, meaning it is in the location and condition necessary to operate as intended, and it continues while the asset is idle unless fully depreciated. It ceases on derecognition or held-for-sale classification, as applicable.'],
            ['PPE-C11', 'Residual value', 'Residual value is the amount an entity would currently obtain from disposing of an asset after estimated disposal costs, assuming the asset were already at the age and condition expected at the end of its useful life. Example: expected proceeds of ₱120,000 less disposal costs of ₱20,000 give residual value of ₱100,000. Review residual value at least annually; revisions based on new information are changes in estimate applied prospectively.'],
            ['PPE-C12', 'Useful life', 'Useful life is the period an asset is expected to be available for use by the entity or the number of production units expected from it. It is not necessarily the asset’s total physical life. Consider expected usage, maintenance, obsolescence, technological change, legal limits, and the entity’s replacement policy. Significant components with different useful lives are depreciated separately. Review the estimate at least annually and apply revisions prospectively.'],
            ['PPE-C13', 'When depreciation starts and stops', 'Depreciation begins when an asset is available for use, not when the final invoice is paid or the first customer order is received. It continues while the asset is idle unless fully depreciated. It stops when the asset is derecognized or classified as held for sale under the applicable requirements. Example: equipment available on 1 April is depreciated from 1 April even if production starts on 1 June.'],
            ['PPE-C14', 'PPE-L06 — Depreciation Methods', 'Choose the depreciation method that reflects the expected pattern of consumption of future economic benefits. Straight-line produces a constant charge when residual value is unchanged; diminishing balance produces higher charges earlier; units of production follows activity. Revenue-based depreciation is generally inappropriate because revenue reflects prices and demand as well as asset consumption. Reassess the method when the expected consumption pattern changes.'],
            ['PPE-C15', 'Diminishing-balance depreciation', 'The diminishing-balance method applies a constant rate to the asset’s opening carrying amount each period. The charge is higher in earlier periods and lower later because the base declines. Example: an opening carrying amount of ₱300,000 at a 40% rate produces ₱120,000 depreciation. Do not apply the rate to original cost every year, and do not reduce carrying amount below residual value.'],
            ['PPE-C16', 'Units-of-production depreciation', 'The units-of-production method links depreciation to actual use. First divide depreciable amount by expected total units, machine hours, or another reliable activity measure; then multiply by current-period activity. Example: cost of ₱1,100,000 less residual value of ₱100,000 over 200,000 units gives ₱5 per unit; 30,000 units produce ₱150,000 depreciation. Revenue is generally not an appropriate consumption measure.'],
            ['PPE-C17', 'PPE-L07 — Changes in Estimates', 'Review useful life, residual value, and depreciation method at least annually because expected usage, condition, technology, and disposal values can change. Use the asset’s existing carrying amount at the review date and revise future depreciation when the estimate changes. A revision based on new information is not automatically a correction of prior periods.'],
            ['PPE-C18', 'Changes in accounting estimate', 'Useful life, residual value, and depreciation method are estimates. When new information changes an estimate, account prospectively rather than restating prior depreciation. Example: if an asset has a carrying amount of ₱600,000 and its remaining useful life is revised to four years with no residual value, future annual depreciation is ₱150,000. Prior depreciation remains unchanged.'],
            ['PPE-C19', 'PPE-L08 — Revaluation', 'Under the revaluation model, PPE is carried at fair value at the revaluation date less subsequent depreciation and impairment. Apply revaluation to the entire class of PPE, not selected favorable assets. If carrying amount rises from ₱8,000,000 to fair value of ₱9,500,000, the increase is ₱1,500,000 before considering prior decreases and tax. Revalue often enough to prevent material differences from fair value.'],
            ['PPE-C20', 'Revaluation surplus', 'An upward revaluation is generally recognized in other comprehensive income and accumulated in equity as revaluation surplus, unless it reverses a previous decrease of the same asset recognized in profit or loss. Depreciation after revaluation is based on the revalued amount and remaining useful life. A transfer from surplus to retained earnings, when made, is within equity and not through profit or loss.'],
            ['PPE-C21', 'Revaluation decrease', 'A revaluation decrease is generally recognized in profit or loss unless a credit balance exists in revaluation surplus for the same asset. Use the related surplus first, then recognize any excess in profit or loss. Example: a ₱250,000 decrease with a related surplus of ₱180,000 reduces the surplus by ₱180,000 and sends ₱70,000 to profit or loss, before applicable tax.'],
            ['PPE-C22', 'PPE-L09 — Impairment', 'When impairment indicators exist, compare carrying amount with recoverable amount. Indicators include a significant decline in market value, adverse technological or legal changes, higher market interest rates, physical damage, obsolescence, planned discontinuance, poor performance, or evidence that the asset will be idle. Recoverable amount is the higher of value in use and fair value less costs of disposal.'],
            ['PPE-C23', 'Recoverable amount', 'Recoverable amount is the higher of fair value less costs of disposal and value in use. Example: if fair value less costs of disposal is ₱620,000 and value in use is ₱700,000, recoverable amount is ₱700,000. Compare this amount with carrying amount; do not add the two measures together and do not use undiscounted cash flows for value in use.'],
            ['PPE-C24', 'Impairment loss', 'Recognize an impairment loss when carrying amount exceeds recoverable amount. For an individual asset, reduce its carrying amount to recoverable amount and recognize the loss as required. Example: carrying amount of ₱900,000 and recoverable amount of ₱700,000 produces a ₱200,000 loss. Revise future depreciation using the new carrying amount, residual value, and remaining useful life.'],
            ['PPE-C25', 'PPE-L10 — Derecognition and Disposal', 'Derecognize PPE on disposal or when no future economic benefits are expected from its use or disposal. Update depreciation through the derecognition date, remove cost and accumulated depreciation or impairment, record net proceeds, and recognize the difference in profit or loss unless another requirement applies. Carrying amount is cost less accumulated depreciation and impairment, not original cost.'],
            ['PPE-C26', 'Gain or loss on disposal', 'Gain or loss equals net disposal proceeds less carrying amount at derecognition. Example: an asset costing ₱1,500,000 with accumulated depreciation of ₱1,100,000 has carrying amount of ₱400,000; sale proceeds of ₱450,000 produce a ₱50,000 gain before selling costs and tax. Update depreciation first and use net, not gross, proceeds.'],
        ];
        foreach ($lessons as [$code, $title, $content]) {
            $concept = Concept::query()->where('subject_id', $subject->id)->where('code', $code)->firstOrFail();
            Lesson::query()->updateOrCreate(['concept_id' => $concept->id, 'title' => $title], ['summary' => $title, 'content' => $content, 'sort_order' => 1]);
        }

        $assessmentData = [
            ['PPE-C08', 'Classify subsequent expenditures as capitalizable replacements or period expenses.', 'Which subsequent expenditure is normally capitalized?', 'A major component replacement that extends useful life', ['Routine oil and filter changes', 'Daily cleaning of the machine', 'Advertising the machine to customers'], 'A qualifying replacement creates separately identifiable future service potential; routine maintenance does not.', 'medium'],
            ['PPE-C09', 'Calculate the carrying amount of PPE under the cost model.', 'Equipment cost is ₱1,000,000, accumulated depreciation is ₱300,000, and accumulated impairment is ₱50,000. What is the carrying amount under the cost model?', '₱650,000', ['₱700,000', '₱950,000', '₱1,350,000'], 'The cost model is cost less accumulated depreciation and accumulated impairment: ₱1,000,000 − ₱300,000 − ₱50,000 = ₱650,000.', 'easy'],
            ['PPE-C10', 'Compute depreciable amount from cost and residual value.', 'Equipment cost is ₱2,000,000 and residual value is ₱200,000. What is the depreciable amount?', '₱1,800,000', ['₱2,200,000', '₱200,000', '₱1,000,000'], 'Depreciable amount equals cost less residual value: ₱2,000,000 − ₱200,000 = ₱1,800,000.', 'easy'],
            ['PPE-C11', 'Determine residual value after considering expected disposal costs.', 'Expected disposal proceeds are ₱120,000 and disposal costs are ₱20,000. What residual value should be used?', '₱100,000', ['₱120,000', '₱140,000', '₱20,000'], 'Residual value is expected disposal proceeds less estimated disposal costs: ₱120,000 − ₱20,000 = ₱100,000.', 'easy'],
            ['PPE-C12', 'Determine useful life using expected economic service, usage, and legal or technological limits.', 'Which factor is most relevant when estimating an asset’s useful life?', 'The period the asset is expected to provide economic service to the entity', ['The longest physical life ever observed for the asset type', 'The date the purchase invoice was paid', 'The asset’s original list price'], 'Useful life is entity-specific and reflects expected economic service, usage, obsolescence, maintenance, and legal limits.', 'medium'],
            ['PPE-C13', 'Identify when depreciation begins and when it ceases.', 'When does depreciation normally begin for PPE?', 'When the asset is available for use', ['When the first customer order is received', 'When the final invoice is paid', 'When the asset reaches full production'], 'Depreciation begins when the asset is in the location and condition necessary to operate as intended.', 'easy'],
            ['PPE-C14', 'Compute straight-line depreciation using cost, residual value, and useful life.', 'Equipment cost is ₱2,000,000, residual value is ₱200,000, and useful life is six years. What is annual straight-line depreciation?', '₱300,000', ['₱333,333', '₱200,000', '₱366,667'], 'Annual depreciation is (₱2,000,000 − ₱200,000) ÷ 6 = ₱300,000.', 'easy'],
            ['PPE-C15', 'Compute diminishing-balance depreciation using the opening carrying amount.', 'An asset has an opening carrying amount of ₱300,000 and a diminishing-balance rate of 40%. What is current-year depreciation?', '₱120,000', ['₱200,000', '₱300,000', '₱750,000'], 'Diminishing-balance depreciation applies the rate to opening carrying amount: ₱300,000 × 40% = ₱120,000.', 'medium'],
            ['PPE-C16', 'Compute units-of-production depreciation from activity and expected total output.', 'Cost is ₱1,100,000, residual value is ₱100,000, expected production is 200,000 units, and actual production is 30,000 units. What is depreciation?', '₱150,000', ['₱30,000', '₱165,000', '₱183,333'], 'Depreciation per unit is ₱1,000,000 ÷ 200,000 = ₱5; current depreciation is 30,000 × ₱5 = ₱150,000.', 'medium'],
            ['PPE-C17', 'Explain why useful life, residual value, and depreciation method are reviewed periodically.', 'Why are useful life and residual value reviewed at least annually?', 'To reflect changes in estimates about future economic benefits', ['To restate all prior depreciation automatically', 'To increase profit whenever possible', 'To avoid recognizing impairment'], 'The review incorporates new information about expected consumption, condition, market values, and disposal costs.', 'easy'],
            ['PPE-C18', 'Apply changes in useful life or residual value prospectively as changes in estimate.', 'A remaining useful life is revised because of new information. How is the revision generally accounted for?', 'As a change in accounting estimate applied prospectively', ['As a prior-period error correction', 'As a change in accounting policy applied retrospectively', 'Only when the asset is disposed'], 'A revised estimate affects current and future depreciation prospectively; prior periods are not normally restated.', 'medium'],
            ['PPE-C19', 'Apply the revaluation model to the entire class of PPE and calculate the revalued amount.', 'A building has a carrying amount of ₱8,000,000 before revaluation and a fair value of ₱9,500,000. What is the revaluation increase?', '₱1,500,000', ['₱8,000,000', '₱9,500,000', '₱17,500,000'], 'The increase is fair value less carrying amount: ₱9,500,000 − ₱8,000,000 = ₱1,500,000.', 'medium'],
            ['PPE-C20', 'Determine when an upward revaluation is recognized in OCI or profit or loss.', 'An asset has an upward revaluation with no related prior decrease recognized in profit or loss. Where is the increase generally recognized?', 'Other comprehensive income and revaluation surplus', ['Revenue from ordinary activities', 'Finance cost', 'Trade payables'], 'An upward revaluation is generally recognized in OCI and accumulated in revaluation surplus unless it reverses a prior decrease in profit or loss.', 'medium'],
            ['PPE-C21', 'Allocate a revaluation decrease between related revaluation surplus and profit or loss.', 'A revaluation decrease is ₱250,000 and the same asset has a revaluation surplus of ₱180,000. What amount generally goes to profit or loss?', '₱70,000', ['₱180,000', '₱250,000', '₱430,000'], 'Use the related surplus first; the excess ₱70,000 is generally recognized in profit or loss.', 'medium'],
            ['PPE-C22', 'Identify internal and external indicators that PPE may be impaired.', 'Which fact is an impairment indicator for PPE?', 'A significant decline in the asset’s market value', ['A routine monthly maintenance invoice', 'A confirmed increase in useful life', 'A favorable change in market demand'], 'A significant decline in market value is an external impairment indicator requiring assessment of recoverable amount.', 'easy'],
            ['PPE-C23', 'Calculate recoverable amount as the higher of value in use and fair value less costs of disposal.', 'Fair value less costs of disposal is ₱620,000 and value in use is ₱700,000. What is recoverable amount?', '₱700,000', ['₱620,000', '₱80,000', '₱1,320,000'], 'Recoverable amount is the higher measure: max(₱620,000, ₱700,000) = ₱700,000.', 'easy'],
            ['PPE-C24', 'Recognize and measure an impairment loss and revise subsequent depreciation.', 'An asset has carrying amount of ₱900,000 and recoverable amount of ₱700,000. What impairment loss is recognized?', '₱200,000', ['₱700,000', '₱900,000', '₱1,600,000'], 'The impairment loss is carrying amount less recoverable amount: ₱900,000 − ₱700,000 = ₱200,000.', 'easy'],
            ['PPE-C25', 'Identify when PPE is derecognized and remove its cost and accumulated depreciation.', 'When is an item of PPE derecognized?', 'On disposal or when no future economic benefits are expected', ['Whenever the asset is temporarily idle', 'When the asset is fully insured', 'When management changes the depreciation method'], 'Derecognize PPE on disposal or when no future economic benefits are expected from use or disposal.', 'easy'],
            ['PPE-C26', 'Compute gain or loss on disposal using net proceeds and carrying amount.', 'An asset costs ₱1,500,000, accumulated depreciation is ₱1,100,000, and sale proceeds are ₱450,000. What is the disposal result?', '₱50,000 gain', ['₱50,000 loss', '₱400,000 gain', '₱1,050,000 gain'], 'Carrying amount is ₱400,000; proceeds of ₱450,000 produce a ₱50,000 gain before selling costs and tax.', 'easy'],
        ];

        foreach ($assessmentData as [$code, $objectiveDescription, $prompt, $correctChoice, $wrongChoices, $explanation, $difficulty]) {
            $concept = Concept::query()->where('subject_id', $subject->id)->where('code', $code)->firstOrFail();
            LearningObjective::query()->updateOrCreate(
                ['concept_id' => $concept->id, 'description' => $objectiveDescription],
                ['sort_order' => $concept->learningObjectives()->count() + 1],
            );

            $question = Question::query()->updateOrCreate(
                ['concept_id' => $concept->id, 'prompt' => $prompt],
                ['explanation' => $explanation, 'difficulty' => $difficulty, 'sort_order' => $concept->questions()->count() + 1],
            );
            foreach (array_merge([$correctChoice], $wrongChoices) as $choiceIndex => $content) {
                QuestionChoice::query()->updateOrCreate(
                    ['question_id' => $question->id, 'content' => $content],
                    ['is_correct' => $choiceIndex === 0, 'sort_order' => $choiceIndex + 1],
                );
            }

            $quiz = Quiz::query()->updateOrCreate(
                ['concept_id' => $concept->id, 'title' => $concept->title.' Quiz'],
                ['description' => 'Test your understanding of '.$concept->title.'.', 'passing_score' => 80],
            );
            $quiz->questions()->syncWithoutDetaching([$question->id => ['sort_order' => $quiz->questions()->count() + 1]]);
        }
    }
}
