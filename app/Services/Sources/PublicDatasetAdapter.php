<?php

namespace App\Services\Sources;

class PublicDatasetAdapter implements SourceAdapterInterface
{
    public function name(): string
    {
        return 'Demo Public Dataset';
    }

    public function collect(array $criteria): array
    {
        // Synthetic records are for demos only and are never mixed into live sourcing by default.
        if (! filter_var($criteria['include_demo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        $role = (string) ($criteria['role'] ?? 'AI Engineer');
        $location = (string) ($criteria['location'] ?? 'Pune, India');
        $skills = $this->skills($criteria);
        $type = (string) ($criteria['candidate_type'] ?? 'Professional');
        $range = $this->experienceRange((string) ($criteria['experience'] ?? '2–5 years'));

        $people = $this->directory();
        $records = [];

        foreach ($people as $index => $person) {
            $experience = $range[0] + (($index * 2) % max(1, ($range[1] - $range[0] + 1)));
            $chosenSkills = $this->pickSkills($skills, $index);
            $records[] = [
                'name' => '[DEMO] '.$person['name'],
                'designation' => $role,
                'company' => $person['company'],
                'experience' => $experience,
                'industry' => (string) ($criteria['industry'] ?? 'Technology'),
                'skills' => $chosenSkills,
                'education' => [['credential' => $person['education'], 'institution' => $person['school'], 'field' => 'Computer Science']],
                'location' => $location,
                'email' => $person['email'],
                'phone' => $person['phone'],
                'profile_url' => 'https://example.edu/profiles/'.$person['slug'],
                'portfolio_url' => $index % 3 === 0 ? 'https://example.com/p/'.$person['slug'] : null,
                'source_url' => 'https://example.edu/profiles/'.$person['slug'],
                'source_name' => $this->name(),
                'candidate_type' => $type,
                'is_synthetic' => true,
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return list<string>
     */
    private function skills(array $criteria): array
    {
        $raw = $criteria['skills'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/[,;]+/', $raw) ?: [];
        }

        $skills = array_values(array_filter(array_map('trim', (array) $raw)));

        return $skills !== [] ? $skills : ['Python', 'SQL', 'Machine Learning'];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function experienceRange(string $label): array
    {
        if (preg_match('/(\d+)\s*[–-]\s*(\d+)/u', $label, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        if (preg_match('/(\d+)\s*\+/', $label, $m)) {
            return [(int) $m[1], (int) $m[1] + 6];
        }

        return [2, 5];
    }

    /**
     * @param  list<string>  $skills
     * @return list<string>
     */
    private function pickSkills(array $skills, int $index): array
    {
        $pool = array_values(array_unique(array_merge($skills, ['Python', 'SQL', 'AWS', 'React', 'Docker'])));
        $picked = [];
        foreach ($skills as $skill) {
            $picked[] = $skill;
        }
        $picked[] = $pool[($index + 3) % count($pool)];

        return array_values(array_unique($picked));
    }

    /**
     * Synthetic directory used only as a permitted demo dataset. These are not real people.
     *
     * @return list<array<string, string>>
     */
    private function directory(): array
    {
        return [
            ['name' => 'Aarav Mehta', 'company' => 'TCS', 'education' => 'B.Tech', 'school' => 'Demo Institute of Technology', 'email' => 'aarav.mehta@synthetic.novamine.demo', 'phone' => '+91 9810000001', 'slug' => 'aarav-mehta'],
            ['name' => 'Ananya Kulkarni', 'company' => 'Infosys', 'education' => 'MCA', 'school' => 'Demo University Pune', 'email' => 'ananya.kulkarni@synthetic.novamine.demo', 'phone' => '+91 9810000002', 'slug' => 'ananya-kulkarni'],
            ['name' => 'Rohan Patil', 'company' => 'Persistent Systems', 'education' => 'BCA', 'school' => 'Demo College of Computing', 'email' => 'rohan.patil@synthetic.novamine.demo', 'phone' => '+91 9810000003', 'slug' => 'rohan-patil'],
            ['name' => 'Ishita Shah', 'company' => 'Accenture', 'education' => 'M.Sc', 'school' => 'Demo Science Faculty', 'email' => 'ishita.shah@synthetic.novamine.demo', 'phone' => '+91 9810000004', 'slug' => 'ishita-shah'],
            ['name' => 'Kabir Khan', 'company' => 'Cognizant', 'education' => 'B.Tech', 'school' => 'Demo Institute of Technology', 'email' => 'kabir.khan@synthetic.novamine.demo', 'phone' => '+91 9810000005', 'slug' => 'kabir-khan'],
            ['name' => 'Meera Joshi', 'company' => 'Capgemini', 'education' => 'MBA', 'school' => 'Demo Business School', 'email' => 'meera.joshi@synthetic.novamine.demo', 'phone' => '+91 9810000006', 'slug' => 'meera-joshi'],
            ['name' => 'Vihaan Deshmukh', 'company' => 'Oracle', 'education' => 'B.Tech', 'school' => 'Demo Institute of Technology', 'email' => 'vihaan.deshmukh@synthetic.novamine.demo', 'phone' => '+91 9810000007', 'slug' => 'vihaan-deshmukh'],
            ['name' => 'Sara Sheikh', 'company' => 'Deloitte', 'education' => 'MCA', 'school' => 'Demo University Pune', 'email' => 'sara.sheikh@synthetic.novamine.demo', 'phone' => '+91 9810000008', 'slug' => 'sara-sheikh'],
            ['name' => 'Aditya Rao', 'company' => 'Microsoft', 'education' => 'B.Tech', 'school' => 'Demo Institute of Technology', 'email' => 'aditya.rao@synthetic.novamine.demo', 'phone' => '+91 9810000009', 'slug' => 'aditya-rao'],
            ['name' => 'Aisha Merchant', 'company' => 'Amazon', 'education' => 'M.Sc', 'school' => 'Demo Science Faculty', 'email' => 'aisha.merchant@synthetic.novamine.demo', 'phone' => '+91 9810000010', 'slug' => 'aisha-merchant'],
            ['name' => 'Arjun Nair', 'company' => 'TCS', 'education' => 'BCA', 'school' => 'Demo College of Computing', 'email' => 'arjun.nair@synthetic.novamine.demo', 'phone' => '+91 9810000011', 'slug' => 'arjun-nair'],
            ['name' => 'Diya Kapoor', 'company' => 'Infosys', 'education' => 'B.Tech', 'school' => 'Demo Institute of Technology', 'email' => 'diya.kapoor@synthetic.novamine.demo', 'phone' => '+91 9810000012', 'slug' => 'diya-kapoor'],
            ['name' => 'Rahul Bhosale', 'company' => 'Persistent Systems', 'education' => 'MCA', 'school' => 'Demo University Pune', 'email' => 'rahul.bhosale@synthetic.novamine.demo', 'phone' => '+91 9810000013', 'slug' => 'rahul-bhosale'],
            ['name' => 'Zoya Ansari', 'company' => 'Cognizant', 'education' => 'M.Sc', 'school' => 'Demo Science Faculty', 'email' => 'zoya.ansari@synthetic.novamine.demo', 'phone' => '+91 9810000014', 'slug' => 'zoya-ansari'],
            ['name' => 'Neil Fernandes', 'company' => 'Capgemini', 'education' => 'B.Tech', 'school' => 'Demo Institute of Technology', 'email' => 'neil.fernandes@synthetic.novamine.demo', 'phone' => '+91 9810000015', 'slug' => 'neil-fernandes'],
        ];
    }
}
