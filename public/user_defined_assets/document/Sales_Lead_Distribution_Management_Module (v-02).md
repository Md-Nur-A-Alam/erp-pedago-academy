# Sales Lead Distribution & Management Module (v-02)

**Product Requirement & Functional Specification**  
*For Tech / Development Team*  
*EdTech Platform Context: Pedago Academy ERP*

---

## 1. Module Purpose
এই মডিউলের উদ্দেশ্য হলো বিভিন্ন Source (Meta Ads, Website, WhatsApp, Phone, Event, Referral ইত্যাদি) থেকে সংগৃহীত Lead সঠিকভাবে Sales Team-এর মধ্যে বিতরণ করা, প্রতিটি Lead-এর Ownership ও Accountability নিশ্চিত করা, সময়মতো Call ও Follow-up সম্পন্ন করা, Lead Loss কমানো, Sales Team-এর মধ্যে Lead Ownership নিয়ে দ্বন্দ্ব প্রতিরোধ করা এবং Conversion-এর পর Student Enrollment ও Installment Payment Workflow স্বয়ংক্রিয় করা।

---

## 2. Core Business Principles

| Principle | Requirement |
| :--- | :--- |
| **One Lead → One Owner** | একই সময়ে একটি Lead-এর একজন মাত্র Active Owner থাকবে। |
| **n-Lead Batch Model (Default n=3)** | একজন Sales Executive-এর কাছে একবারে $n$ টি Lead-এর একটি Batch যাবে। $n$-এর মান শুধুমাত্র Admin পরিবর্তন করতে পারবেন। |
| **Complete Batch → Next Batch** | বর্তমান Batch-এর $n$ টি Task-এর প্রয়োজনীয় Action সম্পন্ন হলে (Follow-up scheduled, Converted, Not Interested, Lost) পরবর্তী $n$ টি Lead বরাদ্দ হবে। |
| **No Feedback → No Completion** | Call/Follow-up-এর পর যথাযথ Feedback Submit না করলে সংশ্লিষ্ট Task Complete হিসেবে গণ্য হবে না। |
| **Every Follow-up Has a Date & Time** | Follow-up প্রয়োজন হলে নির্দিষ্ট Date & Time অবশ্যই সেট করতে হবে। এটি আলাদা Follow-up Queue-তে চলে যাবে এবং নতুন ব্যাচ পাওয়ার ক্ষেত্রে বাধা দেবে না। |
| **Shift/Roster-Based Window & Cut-Off** | প্রতিটি কর্মীর নিজস্ব শিফট/রোস্টার টাইম থাকবে। শিফট শেষ হওয়ার ৩০ মিনিট পূর্বে স্বয়ংক্রিয়ভাবে Lead Assignment বন্ধ হবে। |
| **Every Action Is Recorded** | Call, Feedback, Follow-up, Transfer, Reassignment, Status Change ও Payment Status-এর সম্পূর্ণ অমোচনীয় (Immutable) ইতিহাস থাকবে। |
| **Owner-Based Visibility & Strict Security** | Sales Executive কেবল নিজের Assigned Lead দেখতে ও Action নিতে পারবে। API/Backend লেভেলে Authorization নিশ্চিত করা থাকবে। |
| **Restricted Lead Search** | Sales Executive শুধুমাত্র তার নিজের Assigned Lead-এর অন্তর্ভুক্ত ফোন বা সেকেন্ডারি নম্বরের পূর্ববর্তী কোর্স অনুসন্ধান বা ইতিহাস যাচাই করতে পারবে। |
| **No Lead Left Behind** | Unworked, Overdue বা Unassigned Lead Management-এর সার্বক্ষণিক নজরদারিতে থাকবে। |
| **End-to-End Conversion & Enrollment** | Lead Convert হলে Salesperson Installment সহ Enrollment Form তৈরি করবে এবং Customer Verification ও Payment Gateway/Manual TrxID সহ Accounts Approval সম্পন্ন হবে। |

---

## 3. User Roles & Hierarchy Permissions

| Role | Hierarchy & Main Permissions |
| :--- | :--- |
| **Admin** | পুরো সিস্টেমের পূর্ণ নিয়ন্ত্রণ। সব Lead, User, Shift Configuration, $n$ Batch Size কনফিগারেশন, টিম অ্যাসাইনমেন্ট, রিপোর্ট ও সিস্টেম সেটিংস। |
| **Supervisor** | **One-to-Many Relationship with Teams**: Admin কর্তৃক নির্ধারিত একাধিক নির্দিষ্ট টিমের ওপর Admin-স্তরের সুপারভিশন, ওভারডিউ মনিটরিং ও ট্র্যান্সফার অনুমোদন। |
| **Team Leader** | নিজের নির্দিষ্ট টিমের আওতাধীন Sales Executives, তাদের Current Batch, Overdue, Follow-up, Transfer অনুমোদন ও Reassignment মনিটর/ম্যানেজ করবে। |
| **Sales Executive** | শুধু নিজের Assigned Batch এবং Due Follow-up দেখতে পাবে, কল/ফিডব্যাক দেবে, ট্র্যান্সফার রিকোয়েস্ট করবে এবং কনভার্ট হলে এনরোলমেন্ট লিঙ্ক জেনারেট করবে। |
| **Accounts** | কাস্টমারের জমা দেওয়া পেমেন্ট (অনলাইন গেটওয়ে ট্রানজেকশন অথবা ম্যানুয়াল TrxID ও স্লিপ) যাচাই ও কনফার্ম করবে। |

---

## 4. Lead Data Structure

| Category | Fields |
| :--- | :--- |
| **Basic Information** | Lead ID, Customer/Student Name, Primary Phone, Alternate/Secondary Phone, Email, Location/City, Student Age / Class / Background |
| **EdTech Interest** | Interested Course/Product, Course Category, Student/Parent Type |
| **Source & Campaign** | Lead Source (Meta/FB Ads, Website, WhatsApp, Phone, Event, Referral, Manual), Campaign Name, Ad Set ID, Created Date & Time |
| **Assignment & Batch** | Current Owner, Assigned Date & Time, Assigned By, Previous Owner(s), Batch ID, Batch Completion Status |
| **Follow-up** | Next Follow-up Date & Time, Follow-up Note, Last Action Date |
| **Status** | Lead Status (New, Assigned, Contacted, Follow-up, Interested, Converted, Not Interested, Unreachable, Lost), Transfer Status, Verification Status |

---

## 5. EdTech Smart Duplicate Detection Matrix

যেহেতু প্রতিষ্ঠানটি একটি অনলাইন EdTech প্ল্যাটফর্ম এবং কোর্স সেল করে, তাই একজন অভিভাবকের দুটি ভিন্ন কোর্সের জন্য বা দুই সন্তানের জন্য একই ফোন নম্বরে একাধিক লিড আসতে পারে। সিস্টেমটি স্মার্ট ডুপ্লিকেট চেকিং করবে:

1. **Definite Duplicates (Auto-flagged as Duplicate)**:
   - Primary Phone বা Secondary Phone মিলে গেছে **এবং** কাস্টমার/শিক্ষার্থীর নাম একই **এবং** একই কোর্স **এবং** একই ক্যাম্পেইন/অ্যাড সোর্স।
   - সিস্টেম স্বয়ংক্রিয়ভাবে এটি ডুপ্লিকেট হিসেবে চিহ্নিত করবে।

2. **Cross-Number Match / Potential Inquiries (Admin Review Queue)**:
   - কোনো লিডের Primary Number অন্য লিডের Secondary Number-এর সাথে মিলে গেলে, অথবা একই নম্বরে ভিন্ন কোর্স বা ভিন্ন শিক্ষার্থীর নাম থাকলে।
   - এটি সরাসরি Sales Executive-এর কাছে যাবে না; বরং **Admin / Supervisor Duplicate Review Module**-এ জমা হবে।
   - Admin/Supervisor-এর সিদ্ধান্ত নেওয়ার ক্ষমতা থাকবে:
     - **Approve as Separate Valid Lead**: নতুন লিড হিসেবে ডিস্ট্রিবিউশন কিউতে পাঠানো।
     - **Merge**: বিদ্যমান অ্যাক্টিভ লিডের সাথে মার্জ করা।
     - **Mark Duplicate / Discard**: বাতিল বা ডুপ্লিকেট হিসেবে চিহ্নিত করা।
     - **Edit Lead / Add Feedback**: তথ্য পরিমার্জন করে বণ্টন করা।

---

## 6. Dynamic Roster, Shift & Working Window Engine

* **Variable Office Hours / Shift Roster**:
  - সব এমপ্লয়ির অফিস টাইম এক নয়। সিস্টেমে রস্টার ডিউটি, শিফট ডিউটি ও ওভারটাইম সাপোর্ট থাকবে।
  - প্রতিটি সেলস এক্সিকিউটিভের প্রোফাইলে বা শিফট শিডিউলে `shift_start_time`, `shift_end_time` এবং `is_on_duty_today` নির্ধারিত থাকবে।
* **Assignment Cut-off Window (30 Minutes Rule)**:
  - এক্সিকিউটিভের নিজস্ব শিফট শেষ হওয়ার **৩০ মিনিট আগে** নতুন লিড অ্যাসাইনমেন্ট বন্ধ হয়ে যাবে (যেমন: শিফট সকাল ১০:০০ - সন্ধ্যা ৬:০০ হলে কাট-অফ ৫:৩০; শিফট দুপুর ২:০০ - রাত ১০:০০ হলে কাট-অফ রাত ৯:৩০)।
  - কাট-অফ টাইমের পর কোনো এক্সিকিউটিভ তার বর্তমান ব্যাচ শেষ করলেও ওই দিনে আর নতুন ব্যাচ পাবে না। লিড কেন্দ্রীয় কিউতে থাকবে।
  - Team Leader / Admin প্রয়োজনে শিফটের সময় বা কাট-অফে Manual Override করতে পারবে।

---

## 7. Employee Availability Status

| Status | Meaning |
| :--- | :--- |
| **Available** | অন-ডিউটি এবং শিফট উইন্ডো ওপেন; নতুন ব্যাচ পাওয়ার যোগ্য। |
| **Busy** | বর্তমান ব্যাচের কাজ অসম্পূর্ণ থাকায় নতুন ব্যাচ পাবে না। |
| **Unavailable** | সাময়িকভাবে নতুন লিড দেওয়া বন্ধ (ব্রেক/মিটিং)। |
| **Off Duty** | শিফট আওয়ার্স শেষ অথবা ছুটির দিন; অ্যাসাইনমেন্ট বন্ধ। |
| **Lazy** | টানা ২ কার্যদিবস বা তদূর্ধ্ব সময় ব্যাচ ইনকমপ্লিট বা বিজি থাকলে স্বয়ংক্রিয় সতর্কতা ফ্ল্যাগ। |
| **Resign** | রিজাইন করেছেন; তার কাছে থাকা লিড রিরুট হবে এবং ভবিষ্যতে আর কখনো লিড পাবেন না। |

---

## 8. Lead Distribution Engine — $n$-Lead Batch Model (Default $n=3$)

* **Strict Batching**:
  - একজন সেলস এক্সিকিউটিভকে একবারে $n$ টি Lead বরাদ্দ দেওয়া হবে ($n$-এর ডিফল্ট মান ৩, যা কেবল Admin পরিবর্তন করতে পারবেন)।
* **Batch Completion Rule**:
  - যখন একজন এক্সিকিউটিভ তার বর্তমান ব্যাচের সবকটি ($n$ টি) লিডের ওপর যথাযথ অ্যাকশন/ফিডব্যাক সাবমিট করবেন (যেমন: ১টি Follow-up শিডিউল করলেন, ১টি Converted হলো, ১টি Not Interested মার্ক করলেন), তখন সম্পূর্ণ ব্যাচটি **Completed** হিসেবে গণ্য হবে।
  - ব্যাচ কমপ্লিট হওয়ার সাথে সাথে ডিস্ট্রিবিউশন ইঞ্জিন কেন্দ্রীয় কিউ থেকে পরবর্তী $n$ টি লিড স্বয়ংক্রিয়ভাবে তার কাছে পৌঁছে দেবে (যদি তার শিফট উইন্ডো ওপেন থাকে)।
* **Carry Forward**:
  - শিফট শেষ হওয়ার সময় ব্যাচ অসম্পূর্ণ থাকলে কোনো লিড নষ্ট হবে না; পরবর্তী কার্যদিবসে শিফট শুরু হলে সেই অসম্পূর্ণ লিডগুলোই তার কারেন্ট ব্যাচ হিসেবে থাকবে।

---

## 9. Follow-up Management & Queue Isolation

* **Follow-up Isolation**:
  - যখন কোনো লিডের জন্য Follow-up Date & Time সেট করা হয়, তখন সেটি এক্সিকিউটিভের বর্তমান অ্যাক্টিভ ব্যাচ থেকে মুক্ত হয়ে আলাদা **Follow-up Queue**-তে চলে যায়।
  - এটি নতুন $n$-Lead ব্যাচ বরাদ্দের ক্ষেত্রে কোনো ব্লকার তৈরি করবে না।
* **Due & Reminder**:
  - নির্ধারিত তারিখ ও সময়ে লিডটি এক্সিকিউটিভের `Follow-up Due` সেকশনে অ্যালার্ট সহ চলে আসবে।
  - নির্ধারিত সময় অতিক্রম করলে তা `Overdue` ফ্ল্যাগ পাবে এবং টিম লিডার ড্যাশবোর্ডে হাইলাইট হবে।

---

## 10. Lead Action & Mandatory Feedback

কল বা যোগাযোগের পর নিচের তথ্যগুলো সাবমিট করা বাধ্যতামূলক:

| Field | Options / Requirement |
| :--- | :--- |
| **Call Status** | Connected / Not Connected / Busy / Call Later / Wrong Number / Connected WA (WhatsApp) / Under Age / Over Age |
| **Customer Interest** | Highly Interested / Interested / Maybe / Not Interested / Decision Pending |
| **Customer Concern** | Text (কাস্টমারের নির্দিষ্ট প্রয়োজন বা আপত্তি) |
| **Sales Note** | Text বিস্তারিত বিবরণ |
| **Next Action** | Follow-up / Converted / Not Interested / Unreachable / Lost |
| **Follow-up Date & Time** | Next Action = Follow-up হলে বাধ্যতামূলক |

---

## 11. Lead Transfer & Reassignment

* **Transfer with Approval**:
  - একজন সেলস এক্সিকিউটিভ প্রয়োজনবোধে তার টিম লিডার বা সুপারভাইজারের অনুমোদন সাপেক্ষে লিড অন্য যেকোনো টিমের এক্সিকিউটিভের কাছে ট্র্যান্সফার করতে পারবে।
  - ট্র্যান্সফারের সময় `Transfer Reason` দেওয়া বাধ্যতামূলক।
  - অনুমোদন হওয়ার সাথে সাথে নতুন ওনার লিডটি দেখতে পাবে এবং পূর্বের ওনারের এডিট/অ্যাকশন অ্যাক্সেস বন্ধ হয়ে যাবে।
* **Reassignment**:
  - কোনো কর্মী ছুটিতে থাকলে, দায়িত্ব পালনে ব্যর্থ হলে বা রিজাইন করলে টিম লিডার বা অ্যাডমিন লিড রি-অ্যাসাইন করতে পারবেন।
  - সম্পূর্ণ হিস্ট্রি অপরিবর্তনীয় থাকবে।

---

## 12. Lead Search Privilege (Restricted Access)

* **Anti-Poaching / Context Check Rule**:
  - সেলস এক্সিকিউটিভরা কাস্টমারের প্রাইমারি বা সেকেন্ডারি ফোন নম্বর দিয়ে সার্চ করতে পারবে **শুধুমাত্র এবং শুধুমাত্র যদি** ওই ফোন নম্বরটি বর্তমানে তার কাছে অ্যাসাইন করা কোনো সক্রিয় বা ফলো-আপ লিডে বিদ্যমান থাকে।
  - এর মাধ্যমে সে নিশ্চিত হতে পারবে পূর্বে এই কাস্টমারের অন্য কোনো কোর্সে আগ্রহ ছিল কি না বা অতীতে কার সাথে কী কথা হয়েছিল, কিন্তু অন্যের সাধারণ লিড লিস্ট সে ব্রাউজ করতে পারবে না।

---

## 13. Conversion, Student Enrollment & Payment Workflow

যখন কোনো লিডের স্ট্যাটাস **Converted** হবে, তখন পূর্ণাঙ্গ এনরোলমেন্ট ও পেমেন্ট প্রসেস শুরু হবে:

1. **Enrollment Form Generation**:
   - সেলসপার্সন লিডের ডেটা দিয়ে একটি এনরোলমেন্ট ফর্ম পূরণ করবে:
     - Student Name, Age, Background, Guardian Name & Contact
     - Enrolled Course, Batch, Course Duration
     - Base Course Fee, Scholarship/Discount %, Final Deal Amount
     - **Installment Breakdown**:
       - কিস্তির সংখ্যা (১ম, ২য়, ৩য়... কিস্তি)
       - প্রতিটি কিস্তির জন্য নির্ধারিত টাকা (Amount) এবং পরিশোধের শেষ তারিখ (Due Date), যতক্ষণ পর্যন্ত পুরো Deal Amount পূর্ণ না হয়।
2. **Secure Verification Link Generation**:
   - সিস্টেম স্বয়ংক্রিয়ভাবে একটি ইউনিক ক্রিপ্টোগ্রাফিক ভেরিফিকেশন লিঙ্ক তৈরি করবে (যেমন: `/enroll/verify/{secure_uuid}`)।
   - সেলসপার্সন লিঙ্কটি কাস্টমারকে WhatsApp/SMS/Email-এ পাঠিয়ে দেবে।
3. **Customer Portal & Review**:
   - কাস্টমার লিঙ্কে প্রবেশ করে নিজের নাম, ইমেইল ও সাধারণ তথ্যের বানান ভুল থাকলে সংশোধন করতে পারবে।
   - ফি বা কিস্তির হিসেবে অমিল দেখলে লিঙ্কে থাকা সহায়তার মাধ্যমে সরাসরি সেলসপার্সনের সাথে যোগাযোগ করবে।
4. **Payment Processing**:
   - **Online Payment Gateway**: bKash, SSLCommerz, অথবা AamarPay-এর মাধ্যমে স্বয়ংক্রিয় অনলাইন পেমেন্ট।
   - **Manual Payment**: bKash/Nagad/Rocket/Bank ট্রান্সফার সিলেক্ট করে কাস্টমার Transaction ID (TrxID) এবং পেমেন্ট স্লিপের স্ক্রিনশট আপলোড করবে।
5. **Accounts Verification & Approval**:
   - পেমেন্ট জমা হওয়ার পর এটি Accounts ড্যাশবোর্ডে "Pending Verification" হিসেবে শো করবে।
   - একাউন্টস টিম ব্যাংক/এমএফএস স্টেটমেন্ট চেক করে পেমেন্ট **Confirm** করবে।
   - পেমেন্ট কনফার্ম হলে শিক্ষার্থীর ভর্তি সম্পন্ন হবে এবং স্বয়ংক্রিয় মানি রিসিট ইস্যু হবে।

---

## 14. Architecture & Technical Guidelines

* **Backend**: Laravel 13 on PHP 8.5.
* **Authentication**: Laravel Breeze (Blade + Tailwind CSS v4) ও Laravel Sanctum (API Token Authentication)।
* **API & Blade Modularity**:
  - `app/Modules/Lead/` ডোমেইন ভিত্তিক সার্ভিস লেয়ার (`LeadDistributionEngine`, `DuplicateDetectionService`, `LeadTransferService`, `EnrollmentService`)।
  - Web Controllers (Blade ইন্টারফেস) এবং API Controllers (RESTful JSON Resources) উভয়ই অভিন্ন সার্ভিস লেয়ার ব্যবহার করবে।
* **Reusable Blade Components**:
  - Atomic UI: `<x-ui.card>`, `<x-ui.badge>`, `<x-ui.modal>`, `<x-ui.button>`।
  - Domain Specific: `<x-lead.status-badge>`, `<x-lead.batch-progress>`, `<x-lead.timeline>`, `<x-lead.feedback-modal>`, `<x-lead.installment-table>`।
* **Security & Authorization**:
  - `LeadPolicy`-তে Owner-Based এবং Role-Based রুলস কঠোরভাবে প্রয়োগ করা যাতে আইডি অনুমান করে অন্য কারও ডেটা এক্সেস করা অসম্ভব হয়।

---

## 15. Acceptance Criteria (Checklist)

1. একজন সেলস এক্সিকিউটিভকে একবারে ঠিক $n$ টি লিডের একটি সক্রিয় ব্যাচ দেওয়া হবে।
2. ব্যাচের সবগুলো লিডের অ্যাকশন শেষ না হওয়া পর্যন্ত পরবর্তী ব্যাচ স্বয়ংক্রিয়ভাবে আসবে না।
3. কর্মীর নিজস্ব শিফট শেষ হওয়ার ৩০ মিনিট পূর্বে স্বয়ংক্রিয়ভাবে লিড বণ্টন বন্ধ হবে।
4. ফলো-আপে যাওয়া লিড আলাদা কিউতে যাবে এবং কারেন্ট ব্যাচ রিলিজ করে দেবে।
5. একই নম্বরে ভিন্ন কোর্স বা ভিন্ন শিক্ষার্থীর নাম থাকলে তা অ্যাডমিন রিভিউ কিউতে যাবে।
6. কনভার্শনের পর এনরোলমেন্ট ও কিস্তির হিসাব সহ সিকিউর কাস্টমার লিঙ্ক তৈরি হবে।
7. কাস্টমার অনলাইন গেটওয়ে বা ম্যানুয়াল TrxID দিয়ে পেমেন্ট জমা দিলে একাউন্টস তা অনুমোদন করবে।
8. কোনো অ্যাক্টিভিটি হিস্ট্রি বা ফিডব্যাক কখনো ডিলিট করা যাবে না (Immutable History)।

---
*End of Specification (v-02)*
