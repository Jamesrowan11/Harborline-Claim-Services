<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home()             { return view('site.home'); }
    public function howItWorks()       { return view('site.how-it-works'); }
    public function process()          { return view('site.process'); }
    public function faq()              { return view('site.faq'); }
    public function about()            { return view('site.about'); }
    public function whyContacted()     { return view('site.why-contacted'); }
    public function schedule()         { return view('site.schedule'); }
    public function contact()          { return view('site.contact'); }
    public function referrals()        { return view('site.referrals'); }
    public function accessibility()    { return view('site.legal.accessibility'); }
    public function privacy()          { return view('site.legal.privacy'); }
    public function terms()            { return view('site.legal.terms'); }
    public function disclaimer()       { return view('site.legal.disclaimer'); }
    public function eConsent()         { return view('site.legal.e-consent'); }
    public function smsTerms()         { return view('site.legal.sms-terms'); }
    public function documentSecurity() { return view('site.legal.document-security'); }
    public function scamAwareness()    { return view('site.scam-awareness'); }

    public function contactSubmit(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:5000'],
            'website' => ['prohibited'], // honeypot
        ]);

        Communication::query()->create([
            'channel' => 'email',
            'direction' => 'inbound',
            'sender' => $data['email'],
            'subject' => 'Website contact form: '.$data['name'],
            'body_rendered' => $data['message']."\n\nPhone: ".($data['phone'] ?? 'not given'),
            'status' => 'received',
        ]);

        return back()->with('status', __('Thank you. Your message has been received and a member of our team will reply soon.'));
    }
}
