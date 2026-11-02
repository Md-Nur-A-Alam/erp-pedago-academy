**Sales Lead Distribution & Management Module**

**Product Requirement & Functional Specification**

*For Tech / Development Team*

# **1\. Module Purpose**

এই মডিউলের উদ্দেশ্য হলো বিভিন্ন Source থেকে সংগৃহীত Lead সঠিকভাবে Sales Team-এর মধ্যে বিতরণ করা, প্রতিটি Lead-এর Ownership ও Accountability নিশ্চিত করা, সময়মতো Call ও Follow-up সম্পন্ন করা, Lead Loss কমানো এবং Sales Team-এর মধ্যে Lead Ownership নিয়ে দ্বন্দ্ব প্রতিরোধ করা।

# **2\. Core Business Principles**

| Principle | Requirement |
| :---- | :---- |
| One Lead → One Owner | একই সময়ে একটি Lead-এর একজন মাত্র Active Owner থাকবে। |
| n-Lead Batch Model (currently n=3) | একজন Sales Executive-এর কাছে একবারে nটি Lead-এর একটি Batch যাবে। (currently n=3) |
| Complete Batch → Next Batch | বর্তমান Batch-এর ৩টি Task-এর প্রয়োজনীয় Action সম্পন্ন হলে পরবর্তী ৩টি Lead আসবে। |
| No Feedback → No Completion | Call/Follow-up-এর পর Feedback Submit না করলে সংশ্লিষ্ট Task Complete হিসেবে গণ্য হবে না। |
| Every Follow-up Has a Date & Time | Follow-up প্রয়োজন হলে নির্দিষ্ট Date & Time অবশ্যই সেট করতে হবে। |
| Every Action Is Recorded | Call, Feedback, Follow-up, Transfer, Reassignment ও Status Change-এর History সংরক্ষিত থাকবে। |
| Owner-based Visibility | Sales Executive কেবল নিজের Assigned Lead দেখতে ও Action নিতে পারবে। |
| No Lead Left Behind | Unworked, Overdue বা Unassigned Lead Management-এর নজরদারিতে থাকবে। |
| Lead Search | একটা Lead এর primary and alternate, both contact number কার কাছে assign করা আছে। তবে শর্ত হচ্ছে শুধুমাত্র এই কাজটা কোনো sales executive ই করতে পারবে যার কাছে assign হওয়া lead গুলার মধ্যে এই নম্বর আছে। সে শুধু এইটা শিউর হতে চায় যে, এই একই নম্বরের আগে বা এখন বা অন্য কোনো কোর্সের জন্য লিড ছিলো কিনা বা অন্য কেউ কথা বলেছে কিনা। |

# **3\. User Roles & Permissions**

| Role | Main Permissions |
| :---- | :---- |
| Admin | সব Lead, User, Assignment, Reassignment, Transfer, Report ও Configuration-এর পূর্ণ Access। |
| Supervisor | Can do the same admin operations on some specific teams. And those teams are fixed by the admin too. |
| Team Leader / Sales Manager | নিজের Team-এর Lead, Performance, Overdue, Follow-up, Transfer ও Reassignment Monitor/Manage করতে পারবে। |
| Sales Executive | শুধু নিজের Assigned Lead ও Follow-up Task দেখতে, Call/Feedback দিতে এবং অনুমোদিত নিয়মে Transfer করতে পারবে। |

# **4\. Lead Data Structure**

| Category | Fields |
| :---- | :---- |
| Basic Information | Lead ID, Customer Name, Phone, Alternate Phone, Email, Location |
| Interest | Interested Product/Course, Student/Age Information (যদি প্রযোজ্য) |
| Source | Lead Source, Campaign Name, Created Date & Time |
| Assignment | Current Owner, Assigned Date & Time, Assigned By, Previous Owner |
| Follow-up | Next Follow-up Date & Time, Last Action Date |
| Status | Current Lead Status, Transfer Status |

# **5\. Lead Collection & Upload**

Admin বিভিন্ন Source থেকে Lead সংগ্রহ করে System-এ Upload করবে। প্রথম পর্যায়ে Excel/CSV Upload এবং Manual Entry রাখা যেতে পারে।

Possible Lead Sources:

* Facebook/Meta Ads  
* Website / Landing Page  
* WhatsApp  
* Phone  
* Campaign / Event  
* Referral  
* Manual Entry  
* Other

Upload-এর সময় System Required Field Validation, Phone Validation এবং Duplicate Detection করবে।

# **6\. Duplicate Lead Management**

Primary duplicate matching criterion হবে Phone Number। একই Phone Number পাওয়া গেলে System Existing Lead শনাক্ত করবে। Admin Existing Lead Update, New Lead হিসেবে রাখা, Duplicate Mark বা Merge করার সিদ্ধান্ত নিতে পারবে। একই Customer-এর Duplicate Lead যেন একই সময়ে একাধিক Sales Executive-এর কাছে Active না থাকে—এটি বাধ্যতামূলক Business Rule।  
বিশেষ সতর্কতা, কিছু কিছু ক্ষেত্রে এমনও হতে পারে- একটি লিডের প্রাইমারি কন্টাক্ট নম্বর আরেকটি লিডের সেকেন্ডারি নম্বরে থাকতে পারে। সেক্ষেত্রেও এটা admin এর কাছে একটা বিশেষ মডিউলে যাবে। যেখান থেকে admin সিদ্ধান্ত নিতে পারবেন যে তিনি কোন লিড রাখবেন বা ডিলিট করবেন বা মার্জ করবেন বা এডিট করবেন বা কোনো ফিডব্যাক অ্যাড করবেন।

# **7\. Lead Distribution Engine — n-Lead Batch Model** **(currently n=3)**

Lead Distribution হবে Batch ভিত্তিক। একজন Sales Executive-এর কাছে একবারে nটি Lead Assign হবে। এটি 'n Active Leads সবসময় থাকবে' এমন Model নয়; বরং 'nটি Lead-এর Batch → Batch Completion → পরবর্তী nটি Lead' Model।  (currently n=3)

Example:

* Batch 01: Lead A, Lead B, Lead C  
* A, B, C-এর প্রয়োজনীয় Action Complete হলে Batch 01 Complete হবে।  
* তারপর Batch 02: Lead D, Lead E, Lead F Assign হবে।

# **8\. Distribution Logic**

System Round Robin / Balanced Distribution ব্যবহার করতে পারে। তবে Distribution-এর আগে Executive-এর Availability, Current Batch Status এবং Assignment Window যাচাই করতে হবে।

| Condition | System Action |
| :---- | :---- |
| Executive Available \+ Assignment Window Open | পরবর্তী Eligible Executive-কে ৩টি Lead Assign করবে। |
| Executive-এর Current Batch Incomplete | নতুন Batch Assign করবে না। |
| Current Batch Complete | Queue থেকে পরবর্তী ৩টি Lead Assign করবে। |
| Assignment Window Closed | নতুন Lead Assign করবে না; Lead Queue-তে থাকবে। |
| Executive Unavailable | তাকে Skip করে পরবর্তী Available Executive-এ Lead যাবে। |

# **9\. Assignment Window & End-of-Day Control**

Office শেষ হওয়ার ৩০ মিনিট আগে নতুন Lead Assignment বন্ধ হবে। এই সময়টি System Configuration-এ পরিবর্তনযোগ্য হবে। উদাহরণ: Office Time 10:00 AM–6:00 PM হলে Lead Assignment Cut-off হবে 5:30 PM।

5:30 PM-এর পর কোনো Sales Executive তার বর্তমান Batch Complete করলেও নতুন ৩টি Lead পাবে না। পরবর্তী Working Day-তে Assignment Window পুনরায় Open হলে Queue থেকে Lead Distribution শুরু হবে।

প্রতিটি Sales Executive-কে নিজের ইচ্ছায় Assignment বন্ধ করার ক্ষমতা না দেওয়াই Recommended। System Automatic Cut-off ব্যবহার করবে; Team Leader প্রয়োজনে Manual Override করতে পারবে।

# **10\. Employee Availability Status**

| Status | Meaning |
| :---- | :---- |
| Available | নতুন Batch পাওয়ার জন্য Eligible। |
| Busy | বর্তমান কাজের কারণে নতুন Batch পাবে না। |
| Unavailable | সাময়িকভাবে নতুন Lead পাবে না। |
| Off Duty | Office Hours শেষ; Assignment বন্ধ। |
| Lazy | If busy for consecutive 2 days or more |
| Resign | ইতোমধ্যে রিজাইন করেছেন। ওনাকে আর কখনোই লিড দেওয়া হবে না।  |

# **11\. Lead Assignment**

Lead Assign হওয়ার সঙ্গে সঙ্গে Current Owner, Assigned Date & Time এবং Assignment Source/Actor সংরক্ষণ হবে। Leadটি Sales Executive-এর My Tasks-এ দেখা যাবে এবং অন্য Sales Executive-এর কাছে Invisible থাকবে।

# **12\. Sales Executive — My Tasks**

| Section | Contents |
| :---- | :---- |
| Current Batch | বর্তমান nটি Lead এবং প্রতিটির Action Status। (currently n=3) |
| Follow-up Due | আজকের Follow-up এবং নির্ধারিত সময়ে Due হওয়া Follow-up। |
| Overdue | মিস হওয়া Action বা Follow-up। |
| Upcoming | ভবিষ্যৎ নির্ধারিত Follow-up। |

# **13\. Lead Action & Feedback**

Lead Open করলে Customer Information, Lead Source, Interest, Current Status, Last Action, Notes এবং Next Follow-up দেখা যাবে।

Call/Follow-up-এর পর Feedback Form পূরণ করা বাধ্যতামূলক।

| Feedback Field | Options / Requirement |
| :---- | :---- |
| Call Status | Connected / Not Connected / Busy / Call Later / Wrong Number/ connected WA(WhatsApp) / under Age/ Over Age |
| Customer Interest | Highly Interested / Interested / Maybe / Not Interested/ Decision Pending |
| Customer Requirement / Concern | Text |
| Sales Note | Text / Admission Done |
| Next Action | Follow-up / Converted / Not Interested / Unreachable / Lost |
| Follow-up Date & Time | Next Action \= Follow-up হলে Mandatory |

# **14\. Mandatory Feedback Rule**

Feedback Submit না করলে সংশ্লিষ্ট Task Complete হবে না। System নতুন Batch Release করার ক্ষেত্রে বর্তমান Batch-এর Completion Rule প্রয়োগ করবে। তবে 'Completed' বলতে নির্ধারিত Action/Outcome যথাযথভাবে Record হওয়া বোঝাবে; শুধু Customer-কে কল করাই Completion হিসেবে গণ্য হবে না।

# **15\. Follow-up Management**

Customer এখনই Conversion না করলে Sales Executive নির্দিষ্ট Follow-up Date & Time সেট করবে। নির্ধারিত সময় এলে Leadটি Sales Executive-এর Follow-up Due Task-এ ফিরে আসবে। Follow-up Lead আলাদা Follow-up Queue-তে থাকবে; এটি নতুন n-Lead Batch-এর অংশ হবে না। (currently n=3)

Follow-up Cycle:

* Call → Feedback → Follow-up Date/Time → Reminder → Follow-up → New Feedback → Next Action

# **16\. Follow-up Reminder & Overdue**

System Upcoming, Due এবং Overdue Follow-up আলাদাভাবে দেখাবে। Team Leader Dashboard-এও Team-এর Due ও Overdue Follow-up দেখা যাবে।

# **17\. Batch Completion & Carry Forward Rule**

বর্তমান Batch-এর nটি Lead-এর প্রত্যেকটির জন্য প্রয়োজনীয় Action/Outcome Record সম্পন্ন হলে Batch Complete হবে এবং পরবর্তী nটি Lead Distribution Queue থেকে আসবে। (currently n=3)

Office-এর Assignment Cut-off-এর সময় যদি Batch অসম্পূর্ণ থাকে, কোনো Lead হারাবে না। Pending Task পরবর্তী Working Day-তে Carry Forward হবে। নতুন Batch দেওয়ার আগে Pending Batch-এর Completion Rule প্রয়োগ করতে হবে।

# **18\. Lead Status Definitions**

| Status | Definition |
| :---- | :---- |
| New | Lead এসেছে কিন্তু এখনো Assign/Action হয়নি। |
| Assigned | Sales Executive-এর কাছে Assign হয়েছে। |
| Contacted | Customer-এর সঙ্গে যোগাযোগ হয়েছে। |
| Follow-up | পরবর্তী যোগাযোগের তারিখ নির্ধারিত। |
| Interested | Customer Product/Course-এ আগ্রহী। |
| Converted | Sale/Admission সফল হয়েছে। |
| Not Interested | Customer আগ্রহী নয়। |
| Unreachable | চেষ্টা করেও Customer-এর সঙ্গে যোগাযোগ করা যায়নি। |
| Lost | Lead-এর Conversion সম্ভাবনা শেষ। |

# **19\. Lead Transfer**

Sales Executive প্রয়োজন হলে তার নিজের team leader বা supervisor এর approval নিয়ে নিজের Assigned Lead অন্য Sales Executive-এর কাছে Transfer করতে পারবে। হতে পারে সেই অন্য Sales Executive অন্য কোনো  team leader বা supervisor এর আওতাধীন। Transfer-এর সময় Reason Mandatory হবে। Transfer সম্পন্ন হলে New Owner Leadটি Access করতে পারবে এবং Previous Owner-এর Access বন্ধ হবে।

| Transfer Data | Requirement |
| :---- | :---- |
| Previous Owner | Mandatory |
| New Owner | Mandatory |
| Transfer Reason | Mandatory |
| Approved By | Mandatory |
| Transfer Date & Time | Auto-recorded |
| Transferred By | Auto-recorded |
| Previous Feedback/History | Retained and visible to authorized new owner |

# **20\. Transfer Conflict Prevention**

একটি Lead একই সময়ে একাধিক Active Owner-এর কাছে থাকতে পারবে না। Transfer Complete হওয়ার আগে Ownership Change হবে না। Transfer History Delete করা যাবে না। এতে 'Leadটি আগে কার ছিল'—এই ধরনের বিরোধ System Record দ্বারা সমাধান করা যাবে।

# **21\. Lead Inactivity / Overdue Monitoring**

Lead Assign হওয়ার পর নির্ধারিত ‘t’ সময়ের মধ্যে প্রথম Action না নিলে Lead Overdue হিসেবে Flag হবে। একইভাবে নির্ধারিত Follow-up Date পার হলে Follow-up Overdue হবে। Team Leader Dashboard-এ এগুলো আলাদাভাবে দেখা যাবে। (‘t’ could be hour or day)

# **22\. Reassignment**

Team Leader/Admin প্রয়োজন হলে Inactive, Overdue, Leave বা অন্যান্য operational কারণে Lead Reassign করতে পারবে। Reassignment-ও Activity History-তে Record হবে।

# **23\. Auto Reassignment — Optional Phase 3**

ভবিষ্যতে নির্দিষ্ট Inactivity Threshold অতিক্রম করলে System Team Leader-কে Alert করতে পারে এবং Approval-এর পর Auto Reassignment করতে পারে। প্রথম Phase-এ Manual Reassignment রাখাই Recommended।

# **24\. Lead Ownership Protection**

| Record | Must Be Stored |
| :---- | :---- |
| Lead Creation | Created By, Date & Time |
| Assignment | Assigned To, Assigned By, Date & Time |
| First Contact | First Contacted By, Date & Time |
| Current Ownership | Current Owner |
| Previous Ownership | Previous Owner(s) |
| Transfer | Transfer History |
| Conversion | Conversion Owner, Date & Time |

# **25\. Lead Activity Timeline**

প্রতিটি Lead-এর chronological Activity Timeline থাকবে। উদাহরণ: Lead Created → Assigned → Call → Feedback → Follow-up Scheduled → Follow-up Due → Follow-up Completed → Next Follow-up / Converted / Lost।

# **26\. End-of-Day Scenario**

উদাহরণ: Office 6:00 PM-এ শেষ এবং Assignment Cut-off 5:30 PM। 5:30 PM-এর পরে নতুন Batch Assign হবে না। 5:30 PM-এর আগে Assigned Batch-এর কাজ Sales Executive প্রয়োজন অনুযায়ী চালিয়ে যেতে পারবে। অসম্পূর্ণ Task পরবর্তী Working Day-তে Pending হিসেবে থাকবে এবং কোনো Lead হারাবে না।

# **27\. Leave / Absence Management**

Sales Executive Leave/Unavailable হলে Team Leader তার Active/Pending Leads ও Due Follow-ups দেখতে পারবে। প্রয়োজনে Leads অন্য Executive-এর কাছে Transfer করা যাবে এবং Transfer History সংরক্ষিত থাকবে।

# **28\. Employee Deactivation**

কোনো Sales Executive Deactivate হলে তার Active/Pending Leads Unassigned/Reassignment Queue-তে যাবে। Admin/Team Leader সেগুলো নতুন Owner-এর কাছে Assign করবে। কোনো Lead হারিয়ে যাবে না।

# **29\. Admin / SuperVisor/ Team Leader Dashboard**

| Metric | Purpose |
| :---- | :---- |
| Total Leads | মোট Lead Volume |
| Unassigned Leads | কোনো Owner নেই এমন Lead |
| Unworked Leads | Assigned কিন্তু Action হয়নি |
| Current Batch | Sales Executive-wise current Batch |
| Follow-up Due | আজকের Due Follow-up |
| Overdue | মিস হওয়া Action/Follow-up |
| Interested | Interested Leads |
| Converted | Converted Leads |
| Lost / Not Interested | Closed Lost Leads |
| Transfer | Transfer Activity |

# **30\. Sales Executive Performance KPI**

| KPI | Description |
| :---- | :---- |
| Leads Received | কত Lead/Batch পেয়েছে |
| First Call Completion | সময়মতো প্রথম Action-এর হার |
| Feedback Completion | Feedback যথাযথভাবে Submit করার হার |
| Follow-up Completion | Due Follow-up সম্পন্ন করার হার |
| Overdue Follow-up | মিস করা Follow-up |
| Interested Leads | Interested Lead সংখ্যা |
| Conversions | Converted Lead সংখ্যা |
| Conversion Rate | Conversion ÷ Qualified/Handled Leads |

# **31\. Lead Source & Campaign Reporting**

Source ও Campaign অনুযায়ী Lead Volume, Interested, Converted, Lost এবং Conversion Rate রিপোর্ট করা যাবে। এতে Marketing Source-এর কার্যকারিতা তুলনা করা সম্ভব হবে।

# **32\. Search & Filter**

Admin/Team Leader Search করতে পারবে:

* Lead ID  
* Customer Name  
* Phone Number  
* Lead Source  
* Campaign  
* Product/Course  
* Sales Executive  
* Status  
* Created Date  
* Follow-up Date

* Lead all info by contact number, secondary contact number

# **33\. Notification System**

| User | Notifications |
| :---- | :---- |
| Sales Executive | New Batch Assigned, Follow-up Due, Follow-up Overdue, Transfer, Reassignment |
| Team Leader | Unworked Lead, Overdue Follow-up, Unassigned Lead, Transfer, Inactivity |

# **34\. Access Control & Security**

Sales Executive শুধু নিজের Assigned Lead দেখতে ও Action নিতে পারবে। Team Leader নিজের Team-এর Lead দেখতে পারবে। supervisor নিজের assign করা Team-গুলোর Lead দেখতে পারবে। Admin সব Lead দেখতে পারবে। Frontend-এর পাশাপাশি Backend/API level-এ Owner-based Authorization বাধ্যতামূলক। অন্য User-এর Lead ID অনুমান করে API Call করলেও Access Denied হতে হবে।

# **35\. Conversion Rules**

Converted করার সময় Conversion Date, Product/Course, Sales Executive এবং প্রয়োজনীয় Customer/Student/Payment Reference সংরক্ষণ করা যাবে। Converted Lead সাধারণভাবে পুনরায় Assign হবে না; Admin প্রয়োজন হলে Reopen করতে পারবে।

# **36\. Reopen Rules**

Converted, Lost বা Not Interested Lead Reopen করার ক্ষমতা Admin/Team Leader-এর থাকবে। Reopen Reason, Reopened By এবং Reopened Date/Time সংরক্ষণ করতে হবে।

# **37\. Immutable Activity History**

Sales Executive পুরোনো Feedback, Call Log, Transfer History বা Activity History Delete করতে পারবে না। ভুল তথ্য সংশোধনের জন্য নতুন Correction Note/Updated Entry রাখা যেতে পারে, কিন্তু Original History অক্ষুণ্ণ থাকবে।

# **38\. Recommended Lead Lifecycle**

Lead Source → Collection → Validation → Duplicate Check → Central Database → Distribution Queue → n-Lead Batch → Call → Feedback → Follow-up / Conversion / Lost → Follow-up Queue → Reminder → Follow-up → Feedback → Final Outcome

# **39\. Core Business Rules — Development Checklist**

| Rule | Requirement |
| :---- | :---- |
| Ownership | One Lead → One Active Owner |
| Distribution | n-Lead Batch |
| Next Batch | Current Batch complete হলে পরবর্তী n Leads |
| Assignment Cut-off | Office end-এর 30 minutes আগে |
| Follow-up | Date & Time mandatory when required |
| Feedback | Action-এর পর mandatory |
| Visibility | Sales Executive sees own Leads only |
| Transfer | Allowed with mandatory reason |
| History | Immutable |
| Duplicate | Phone-based detection |
| Overdue | Required |
| Reminder | Required |
| Reassignment | Admin/Team Leader |
| API Security | Owner-based authorization |

# **40\. Development Phases**

| Phase | Features |
| :---- | :---- |
| Phase 1 — Must Have | Lead Upload, Database, Duplicate Detection, Roles, 3-Lead Batch Distribution, Assignment Window, Ownership, Dashboard, Feedback, Follow-up, Transfer, Status, History, Access Control |
| Phase 2 — Productivity | Overdue Monitoring, Reminders, Team Leader Dashboard, Performance KPI, Source Report, Conversion Report, Reassignment, Leave Handling |
| Phase 3 — Automation | Round Robin Optimization, Auto Reassignment, Advanced Analytics, Lead Scoring, Campaign Analytics, Advanced Notifications |

# **41\. Acceptance Criteria**

1. একজন Sales Executive একসঙ্গে সর্বোচ্চ একটি 3-Lead Batch পাবে।  
2. Batch-এর প্রয়োজনীয় Action সম্পন্ন না হলে নতুন Batch Assign হবে না।  
3. Office end-এর 30 মিনিট আগে Assignment স্বয়ংক্রিয়ভাবে বন্ধ হবে।  
4. Assignment বন্ধ হওয়ার পর Queue-তে Lead থাকবে; কোনো Lead হারাবে না।  
5. Follow-up Lead নির্ধারিত Date/Time-এ My Follow-up Tasks-এ ফিরে আসবে।  
6. একজন Sales Executive অন্য Executive-এর Assigned Lead দেখতে পারবে না।  
7. Transfer-এর ক্ষেত্রে Ownership এবং History সঠিকভাবে Record হবে।  
8. Call/Follow-up-এর পর Feedback ছাড়া Task Complete হবে না।  
9. Overdue Lead/Follow-up Team Leader Dashboard-এ দৃশ্যমান হবে।  
10. Admin/Team Leader প্রয়োজন হলে Lead Reassign করতে পারবে।  
11. Activity History Delete করা যাবে না।  
12. Backend/API-level authorization অন্য Executive-এর Lead Access প্রতিরোধ করবে।

# **42\. Final Product Objective**

এই Module-এর লক্ষ্য শুধু Lead distribute করা নয়; বরং Lead-এর সম্পূর্ণ lifecycle-এর ওপর accountability তৈরি করা। System নিশ্চিত করবে—কোন Lead কার কাছে আছে, কী Action হয়েছে, Customer কী Response দিয়েছে, কখন Follow-up হওয়ার কথা, Follow-up হয়েছে কি না, Lead Transfer হয়েছে কি না এবং শেষ পর্যন্ত Conversion হয়েছে কি না।

**Lead → Assignment → Action → Feedback → Follow-up → Re-action → Transfer → Conversion/Lost**

**End of Specification**